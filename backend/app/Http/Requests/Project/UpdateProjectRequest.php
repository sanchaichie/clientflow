<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use App\Enums\RoleSlug;
use App\Models\Client;
use App\Models\Project;
use App\Rules\ActiveUserWithRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Members are managed through ProjectMemberController, not here. */
class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'required', 'integer', $this->clientIsUsable(...)],
            // Only admins may hand a project to a different manager.
            'manager_id' => [
                Rule::prohibitedIf(! $this->user()->isAdmin()),
                'sometimes', 'required', 'integer', new ActiveUserWithRole(RoleSlug::ProjectManager),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'required', Rule::enum(ProjectStatus::class)],
        ];
    }

    /** Compare dates against stored values when only one of them is sent. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Project $project */
                $project = $this->route('project');
                $start = $this->has('start_date') ? $this->input('start_date') : $project->start_date;
                $end = $this->has('end_date') ? $this->input('end_date') : $project->end_date;

                if ($start && $end && Carbon::parse($end)->lt(Carbon::parse($start))) {
                    $validator->errors()->add('end_date', 'The end date must be on or after the start date.');
                }
            },
        ];
    }

    private function clientIsUsable(string $attribute, mixed $value, Closure $fail): void
    {
        $usable = Client::query()->visibleTo($this->user())->notArchived()->whereKey($value)->exists();

        if (! $usable) {
            $fail('The selected client is invalid or archived.');
        }
    }
}
