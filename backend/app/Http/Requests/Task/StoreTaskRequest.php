<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    private ?Project $project = null;

    public function authorize(): bool
    {
        $project = $this->project();

        // An unknown project is a validation error (422), not an authorization one.
        return $project === null || $this->user()->can('create', [Task::class, $project]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', $this->assigneeBelongsToProject(...)],
        ];
    }

    public function project(): ?Project
    {
        return $this->project ??= Project::find($this->integer('project_id'));
    }

    private function assigneeBelongsToProject(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->project() && ! $this->project()->canBeAssignedTo((int) $value)) {
            $fail('The assignee must be an active member of the project.');
        }
    }
}
