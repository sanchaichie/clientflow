<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Admins pass every check via Gate::before (AppServiceProvider).
 * The project manager controls a project's tasks; team members may move
 * only the tasks assigned to them and comment on any task they can see.
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

    /** Usage: $this->authorize('create', [Task::class, $project]) */
    public function create(User $user, Project $project): bool
    {
        return $project->isManagedBy($user);
    }

    /** Full edit: title, description, assignee, priority, due date, project. */
    public function update(User $user, Task $task): bool
    {
        return $task->project->isManagedBy($user);
    }

    /** Status only (Kanban drag, "mark complete"). */
    public function updateStatus(User $user, Task $task): bool
    {
        return $task->project->isManagedBy($user) || $task->assignee_id === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->project->isManagedBy($user);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->canSeeProject($user, $task->project);
    }

    private function canSeeProject(User $user, Project $project): bool
    {
        return $project->isManagedBy($user) || $project->hasMember($user);
    }
}
