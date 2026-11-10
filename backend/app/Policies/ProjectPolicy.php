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

    /** Includes changing the project's status (spec §3, Project Manager). */
    public function update(User $user, Project $project): bool
    {
        return $project->isManagedBy($user);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $project->isManagedBy($user);
    }

    /** "Delete" archives the project. */
    public function delete(User $user, Project $project): bool
    {
        return $project->isManagedBy($user);
    }

    /** Un-archiving is admin-only. */
    public function restore(User $user, Project $project): bool
    {
        return false;
    }
}
