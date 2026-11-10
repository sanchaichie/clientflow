<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;

class TaskAssigned extends ClientFlowNotification
{
    public function __construct(public Task $task, public ?User $actor) {}

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'task_assigned',
            'message' => ($this->actor?->name ?? 'Someone')." assigned you \"{$this->task->title}\"",
            'url' => "/tasks/{$this->task->id}",
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'actor' => $this->actor?->name,
        ];
    }
}
