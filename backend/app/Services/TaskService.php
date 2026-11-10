<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every task write goes through here so that completed_at, Kanban positions
 * and the project's cached progress always stay consistent.
 * (Activity logging and notifications hook in here in Milestone 6.)
 */
class TaskService
{
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

            return $task;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated UpdateTaskRequest data
     */
    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $newStatus = isset($data['status']) ? TaskStatus::from($data['status']) : null;
            unset($data['status']);

            $task->fill($data)->save();

            if ($newStatus && $newStatus !== $task->status) {
                $this->moveTo($task, $newStatus, null);
            }

            return $task;
        });
    }

    /** Kanban move: change status and/or position within the target column. */
    public function changeStatus(Task $task, TaskStatus $status, ?int $position = null): Task
    {
        return DB::transaction(fn () => $this->moveTo($task, $status, $position));
    }

    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $project = $task->project;
            $task->delete();
            $project->recalculateProgress();
        });
    }

    /**
     * Place the task at $position in the $status column (append when null),
     * shifting the tasks below it down by one.
     */
    private function moveTo(Task $task, TaskStatus $status, ?int $position): Task
    {
        $statusChanged = $task->status !== $status;

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
        }

        return $task;
    }

    private function nextPosition(Project $project, TaskStatus $status): int
    {
        $max = $project->tasks()->where('status', $status)->max('position');

        return $max === null ? 0 : $max + 1;
    }
}
