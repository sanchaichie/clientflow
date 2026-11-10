<?php

namespace App\Http\Requests\Task;

use App\Enums\CommentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('comment', $this->route('task'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            // "work_update" = a team member's progress report (spec §3, "Submit work updates").
            'type' => ['sometimes', Rule::enum(CommentType::class)],
        ];
    }
}
