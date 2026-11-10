<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use App\Enums\RoleSlug;
use App\Models\Client;
use App\Models\Project;
use App\Rules\ActiveUserWithRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isAdmin = $this->user()->isAdmin();

        return [
            'client_id' => ['required', 'integer', $this->clientIsUsable(...)],
            // Admins pick the manager; a PM's new project is always managed by them.
            'manager_id' => [
                Rule::requiredIf($isAdmin), Rule::prohibitedIf(! $isAdmin),
                'integer', new ActiveUserWithRole(RoleSlug::ProjectManager),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer', 'distinct', new ActiveUserWithRole(RoleSlug::TeamMember, RoleSlug::ProjectManager)],
        ];
    }

    /** The client must exist, be visible to the user, and not be archived. */
    private function clientIsUsable(string $attribute, mixed $value, Closure $fail): void
    {
        $usable = Client::query()->visibleTo($this->user())->notArchived()->whereKey($value)->exists();

        if (! $usable) {
            $fail('The selected client is invalid or archived.');
        }
    }
}
