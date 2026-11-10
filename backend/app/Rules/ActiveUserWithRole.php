<?php

namespace App\Rules;

use App\Enums\RoleSlug;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be the id of an active user holding one of the given roles,
 * e.g. new ActiveUserWithRole(RoleSlug::ProjectManager) for a manager field.
 */
class ActiveUserWithRole implements ValidationRule
{
    /** @var list<RoleSlug> */
    private array $roles;

    public function __construct(RoleSlug ...$roles)
    {
        $this->roles = $roles;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = User::query()
            ->whereKey($value)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', $this->roles))
            ->exists();

        if (! $exists) {
            $labels = implode(' or ', array_map(fn (RoleSlug $r) => strtolower($r->label()), $this->roles));
            $fail("The selected :attribute must be an active {$labels}.");
        }
    }
}
