<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The single place that writes activity_logs (spec §4.6).
 *
 * Each entry stores a small snapshot (e.g. the task title) in `properties`, so
 * history stays readable after a record is renamed or deleted.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(?User $actor, string $action, Model $subject, array $properties = []): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $actor?->id,
            'project_id' => $this->projectIdFor($subject),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'properties' => array_merge($this->snapshot($subject), $properties),
        ]);
    }

    private function projectIdFor(Model $subject): ?int
    {
        return match (true) {
            $subject instanceof Project => $subject->id,
            $subject instanceof Task => $subject->project_id,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function snapshot(Model $subject): array
    {
        return match (true) {
            $subject instanceof Task => ['title' => $subject->title],
            $subject instanceof Project => ['name' => $subject->name],
            default => [],
        };
    }
}
