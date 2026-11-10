<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** PATCH /api/tasks/{task}/status — used by the Kanban board. */
class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('task'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
            // Index within the target column (0 = top). Omit to append at the bottom.
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
