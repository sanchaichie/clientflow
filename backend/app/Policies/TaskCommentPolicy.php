<?php

namespace App\Policies;

use App\Models\TaskComment;
use App\Models\User;

/**
 * Viewing and posting comments are covered by TaskPolicy::view / ::comment.
 * Admins pass every check via Gate::before.
 */
class TaskCommentPolicy
{
    /** Authors may delete their own comments while the project is open. */
    public function delete(User $user, TaskComment $comment): bool
    {
        return $comment->user_id === $user->id && ! $comment->task->project->isArchived();
    }
}
