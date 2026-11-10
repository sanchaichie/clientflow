<?php

namespace App\Http\Requests\Client;

use App\Enums\ClientStatus;
use App\Enums\RoleSlug;
use App\Rules\ActiveUserWithRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('client'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', Rule::enum(ClientStatus::class)],
            // Only admins may reassign a client to another account manager.
            'account_manager_id' => [
                Rule::prohibitedIf(! $this->user()->isAdmin()),
                'sometimes', 'nullable', 'integer', new ActiveUserWithRole(RoleSlug::ProjectManager),
            ],
        ];
    }
}
