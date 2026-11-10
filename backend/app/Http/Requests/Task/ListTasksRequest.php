<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** GET /api/tasks filters. Rows are limited by Task::visibleTo(). */
class ListTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Task::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['nullable', 'regex:/^(\d+|me)$/'],
            'due' => ['nullable', Rule::in(['overdue', 'today'])],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string'],
            // Up to 200 so the Kanban board can load a whole project at once.
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
