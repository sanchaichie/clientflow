<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Admins pass every check via Gate::before (AppServiceProvider).
 * The project manager controls a project's tasks; team members may move
 * only the tasks assigned to them and comment on any task they can see.
 * Tasks in an archived project are read-only for everyone but admins.
 */
class TaskPolicy
{
    /** Everyone may list tasks; the list is filtered by Task::visibleTo(). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Keep in sync with Task::scopeVisibleTo(). */
    public function view(User $user, Task $task): bool
    {
        return $this->canSeeProject($user, $task->project);
    }

    /** Usage: Gate::authorize('create', [Task::class, $project]) */
    public function create(User $user, Project $project): bool
    {
        return $this->managesOpenProject($user, $project);
    }

    /** Full edit: title, description, assignee, priority, due date. */
    public function update(User $user, Task $task): bool
    {
        return $this->managesOpenProject($user, $task->project);
    }

    /** Status only (Kanban drag, "mark complete"). */
    public function updateStatus(User $user, Task $task): bool
    {
        return ! $task->project->isArchived()
            && ($task->project->isManagedBy($user) || $task->assignee_id === $user->id);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->managesOpenProject($user, $task->project);
    }

    public function comment(User $user, Task $task): bool
    {
        return ! $task->project->isArchived() && $this->canSeeProject($user, $task->project);
    }

    private function managesOpenProject(User $user, Project $project): bool
    {
        return $project->isManagedBy($user) && ! $project->isArchived();
    }

    private function canSeeProject(User $user, Project $project): bool
    {
        return $project->isManagedBy($user) || $project->hasMember($user);
    }
}
