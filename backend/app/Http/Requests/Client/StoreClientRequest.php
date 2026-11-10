<?php

namespace App\Http\Requests\Client;

use App\Enums\ClientStatus;
use App\Enums\RoleSlug;
use App\Models\Client;
use App\Rules\ActiveUserWithRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Client::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(ClientStatus::class)],
            // Admins choose the account manager; a PM's new client is always theirs.
            'account_manager_id' => [
                Rule::prohibitedIf(! $this->user()->isAdmin()),
                'nullable', 'integer', new ActiveUserWithRole(RoleSlug::ProjectManager),
            ],
        ];
    }
}
