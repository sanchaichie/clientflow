<?php

namespace App\Http\Requests\Client;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** GET /api/clients filters. */
class ListClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Client::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ClientStatus::class)],
            'archived' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
