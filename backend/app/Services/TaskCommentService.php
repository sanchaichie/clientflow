<?php

namespace App\Services;

use App\Enums\CommentType;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\TaskCommented;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskCommentService
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /**
     * Save a comment, record it in the activity log, and notify the task's
     * assignee and the project manager (never the author themselves).
     *
     * @param  array{body: string, type?: string}  $data
     */
    public function create(Task $task, array $data, User $author): TaskComment
    {
        return DB::transaction(function () use ($task, $data, $author) {
            $comment = new TaskComment([
                'body' => $data['body'],
                'type' => $data['type'] ?? CommentType::Comment->value,
            ]);
            $comment->task()->associate($task);
            $comment->author()->associate($author);
            $comment->save();

            $this->activity->log($author, 'task.commented', $task, [
                'comment_id' => $comment->id,
                'type' => $comment->type->value,
                'excerpt' => Str::limit($comment->body, 80),
            ]);

            $this->notifier->send(
                [$task->assignee, $task->project->manager],
                new TaskCommented($task, $comment, $author),
                $author,
            );

            return $comment;
        });
    }
}
