<?php

namespace App\Http\Requests\Project;

use App\Enums\RoleSlug;
use App\Rules\ActiveUserWithRole;
use Illuminate\Foundation\Http\FormRequest;

class AddProjectMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('project'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', new ActiveUserWithRole(RoleSlug::TeamMember, RoleSlug::ProjectManager)],
        ];
    }
}
