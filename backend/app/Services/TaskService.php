<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskStatusChanged;
use Illuminate\Support\Facades\DB;

/**
 * Every task write goes through here so that completed_at, Kanban positions,
 * the project's cached progress, the activity log and notifications always
 * stay consistent. Each method runs in one transaction.
 */
class TaskService
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated StoreTaskRequest data
     */
    public function create(Project $project, array $data, User $creator): Task
    {
        return DB::transaction(function () use ($project, $data, $creator) {
            $status = TaskStatus::from($data['status'] ?? TaskStatus::Todo->value);

            $task = new Task($data);
            $task->project()->associate($project);
            $task->created_by = $creator->id;
            $task->status = $status;
            $task->priority ??= TaskPriority::Medium;
            $task->completed_at = $status === TaskStatus::Completed ? now() : null;
            $task->position = $this->nextPosition($project, $status);
            $task->save();

            $project->recalculateProgress();
            $this->activity->log($creator, 'task.created', $task);

            if ($task->assignee_id) {
                $this->notifier->send([$task->assignee], new TaskAssigned($task, $creator), $creator);
            }

            return $task;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated UpdateTaskRequest data
     */
    public function update(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $newStatus = isset($data['status']) ? TaskStatus::from($data['status']) : null;
            unset($data['status']);

            $task->fill($data);

            $assigneeChanged = $task->isDirty('assignee_id');
            $previousAssignee = $task->getOriginal('assignee_id');
            // A new due date deserves a new "due soon" reminder.
            if ($task->isDirty('due_date')) {
                $task->due_soon_notified_at = null;
            }
            $changedFields = array_keys(array_diff_key($task->getDirty(), array_flip(['assignee_id', 'due_soon_notified_at'])));

            $task->save();

            if ($changedFields) {
                $this->activity->log($actor, 'task.updated', $task, ['fields' => $changedFields]);
            }
            if ($assigneeChanged) {
                $task->load('assignee'); // the cached relation may still point to the old assignee
                $this->activity->log($actor, 'task.assigned', $task, [
                    'from' => $previousAssignee ? User::find($previousAssignee)?->name : null,
                    'to' => $task->assignee?->name,
                ]);
                $this->notifier->send([$task->assignee], new TaskAssigned($task, $actor), $actor);
            }
            if ($newStatus && $newStatus !== $task->status) {
                $this->moveTo($task, $newStatus, null, $actor);
            }

            return $task;
        });
    }

    /** Kanban move: change status and/or position within the target column. */
    public function changeStatus(Task $task, TaskStatus $status, ?int $position, User $actor): Task
    {
        return DB::transaction(fn () => $this->moveTo($task, $status, $position, $actor));
    }

    public function delete(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor) {
            $project = $task->project;
            // Logged before deleting; the title snapshot keeps the entry readable.
            $this->activity->log($actor, 'task.deleted', $task);
            $task->delete();
            $project->recalculateProgress();
        });
    }

    /**
     * Place the task at $position in the $status column (append when null),
     * shifting the tasks below it down by one.
     */
    private function moveTo(Task $task, TaskStatus $status, ?int $position, User $actor): Task
    {
        $previous = $task->status;
        $statusChanged = $previous !== $status;

        if ($position === null) {
            $position = $statusChanged ? $this->nextPosition($task->project, $status) : $task->position;
        } else {
            $task->project->tasks()
                ->where('status', $status)
                ->whereKeyNot($task->id)
                ->where('position', '>=', $position)
                ->increment('position');
        }

        $task->status = $status;
        $task->position = $position;
        if ($statusChanged) {
            $task->completed_at = $status === TaskStatus::Completed ? now() : null;
        }
        $task->save();

        if ($statusChanged) {
            $task->project->recalculateProgress();
            $this->activity->log($actor, 'task.status_changed', $task, [
                'from' => $previous->value,
                'to' => $status->value,
            ]);
            $this->notifier->send(
                [$task->project->manager, $task->assignee],
                new TaskStatusChanged($task, $previous, $status, $actor),
                $actor,
            );
        }

        return $task;
    }

    private function nextPosition(Project $project, TaskStatus $status): int
    {
        $max = $project->tasks()->where('status', $status)->max('position');

        return $max === null ? 0 : $max + 1;
    }
}
