<?php

namespace App\Notifications;

use App\Models\Task;

/** Sent by the daily `tasks:notify-due-soon` command. */
class TaskDueSoon extends ClientFlowNotification
{
    public function __construct(public Task $task) {}

    public function toArray(object $notifiable): array
    {
        $when = $this->task->due_date?->isToday() ? 'today' : 'tomorrow';

        return [
            'kind' => 'task_due_soon',
            'message' => "\"{$this->task->title}\" is due {$when}",
            'url' => "/tasks/{$this->task->id}",
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'due_date' => $this->task->due_date?->toDateString(),
        ];
    }
}
