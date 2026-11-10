<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** GET /api/projects filters. Rows are limited by Project::visibleTo(). */
class ListProjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Project::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'client_id' => ['nullable', 'integer'],
            'manager_id' => ['nullable', 'integer'],
            'archived' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
