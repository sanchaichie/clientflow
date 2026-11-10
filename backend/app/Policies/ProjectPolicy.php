<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Admins pass every check via Gate::before (AppServiceProvider).
 */
class ProjectPolicy
{
    /** Everyone may list projects; the list is filtered by Project::visibleTo(). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Keep in sync with Project::scopeVisibleTo(). */
    public function view(User $user, Project $project): bool
    {
        return $project->isManagedBy($user) || $project->hasMember($user);
    }

    public function create(User $user): bool
    {
        return $user->isProjectManager();
    }

    /**
     * Includes changing the project's status (spec §3, Project Manager).
     * Archived projects are read-only for everyone but admins.
     */
    public function update(User $user, Project $project): bool
    {
        return $project->isManagedBy($user) && ! $project->isArchived();
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /** "Delete" archives the project. */
    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /** Un-archiving is admin-only. */
    public function restore(User $user, Project $project): bool
    {
        return false;
    }
}
