<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** GET /api/activity filters. Rows are limited to projects the user can see. */
class ListActivityRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer'],
            'task_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
