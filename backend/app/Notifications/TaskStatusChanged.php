<?php

namespace App\Notifications;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

class TaskStatusChanged extends ClientFlowNotification
{
    public function __construct(
        public Task $task,
        public TaskStatus $from,
        public TaskStatus $to,
        public ?User $actor,
    ) {}

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'task_status_changed',
            'message' => ($this->actor?->name ?? 'Someone')
                ." moved \"{$this->task->title}\" to {$this->to->label()}",
            'url' => "/tasks/{$this->task->id}",
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'actor' => $this->actor?->name,
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }
}
