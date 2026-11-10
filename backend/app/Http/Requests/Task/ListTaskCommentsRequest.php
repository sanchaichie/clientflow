<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

/** GET /api/tasks/{task}/comments */
class ListTaskCommentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('task'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
