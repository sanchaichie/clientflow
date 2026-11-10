<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Notifications\TaskDueSoon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily reminder for open tasks due today or tomorrow (spec §4.7,
 * "approaching deadlines"). Scheduled in routes/console.php.
 *
 * Each task is reminded once: `due_soon_notified_at` is set after sending
 * and cleared by TaskService when the due date changes.
 */
#[Signature('tasks:notify-due-soon')]
#[Description('Notify assignees about open tasks due today or tomorrow')]
class NotifyTasksDueSoon extends Command
{
    public function handle(): int
    {
        $sent = 0;

        Task::query()
            ->where('status', '!=', TaskStatus::Completed)
            ->whereNull('due_soon_notified_at')
            ->whereBetween('due_date', [today(), today()->addDay()])
            ->whereHas('assignee', fn ($q) => $q->where('is_active', true))
            ->whereHas('project', fn ($q) => $q->whereNull('archived_at'))
            ->with('assignee')
            ->chunkById(200, function ($tasks) use (&$sent) {
                foreach ($tasks as $task) {
                    $task->assignee->notify(new TaskDueSoon($task));
                    $task->forceFill(['due_soon_notified_at' => now()])->saveQuietly();
                    $sent++;
                }
            });

        $this->info("Sent {$sent} due-soon reminder(s).");

        return self::SUCCESS;
    }
}
