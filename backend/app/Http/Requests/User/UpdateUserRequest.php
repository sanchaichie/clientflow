<?php

namespace App\Http\Requests\User;

use App\Enums\RoleSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['sometimes', 'required', Rule::enum(RoleSlug::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** Stop admins from locking themselves out. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->user()->is($this->route('user'))) {
                    return;
                }
                if ($this->has('role') && $this->input('role') !== RoleSlug::Admin->value) {
                    $validator->errors()->add('role', 'You cannot remove your own administrator role.');
                }
                if ($this->has('is_active') && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                }
            },
        ];
    }
}
