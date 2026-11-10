<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A task cannot be moved to another project; delete and recreate instead. */
class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', 'required', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', $this->assigneeBelongsToProject(...)],
        ];
    }

    private function assigneeBelongsToProject(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var Task $task */
        $task = $this->route('task');

        if (! $task->project->canBeAssignedTo((int) $value)) {
            $fail('The assignee must be an active member of the project.');
        }
    }
}
