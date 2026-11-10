<?php

namespace App\Policies;

use App\Models\User;

/**
 * Admins pass every check via Gate::before (AppServiceProvider).
 * User accounts are admin-controlled (spec §4.1): only admins create,
 * update or deactivate users.
 */
class UserPolicy
{
    /** PMs need the user directory to add members and assign tasks. */
    public function viewAny(User $user): bool
    {
        return $user->isProjectManager();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isProjectManager() || $user->is($model);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }

    /** "Delete" deactivates the account; see UserService::deactivate(). */
    public function delete(User $user, User $model): bool
    {
        return false;
    }
}
