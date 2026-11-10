<?php

namespace App\Notifications;

use App\Enums\CommentType;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Str;

class TaskCommented extends ClientFlowNotification
{
    public function __construct(public Task $task, public TaskComment $comment, public User $author) {}

    public function toArray(object $notifiable): array
    {
        $what = $this->comment->type === CommentType::WorkUpdate ? 'posted a work update on' : 'commented on';

        return [
            'kind' => 'task_commented',
            'message' => "{$this->author->name} {$what} \"{$this->task->title}\"",
            'excerpt' => Str::limit($this->comment->body, 120),
            'url' => "/tasks/{$this->task->id}",
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'actor' => $this->author->name,
        ];
    }
}
