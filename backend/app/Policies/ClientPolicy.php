<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Admins pass every check via Gate::before (AppServiceProvider).
 * Team members have no client access; they see a client's name through
 * the projects they belong to.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isProjectManager();
    }

    /** Keep in sync with Client::scopeVisibleTo(). */
    public function view(User $user, Client $client): bool
    {
        return $user->isProjectManager()
            && ($client->isAccountManagedBy($user)
                || $client->projects()->where('manager_id', $user->id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->isProjectManager();
    }

    /** PMs manage only the clients assigned to them (ERD decision C). */
    public function update(User $user, Client $client): bool
    {
        return $user->isProjectManager() && $client->isAccountManagedBy($user);
    }

    /** "Delete" archives the client; see ClientController::destroy(). */
    public function delete(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }

    /** Un-archiving is admin-only. */
    public function restore(User $user, Client $client): bool
    {
        return false;
    }
}
