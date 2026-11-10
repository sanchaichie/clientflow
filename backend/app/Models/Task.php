<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Status changes go through TaskService (Milestone 4) so completed_at,
// project progress, activity logs and notifications stay consistent.
#[Fillable(['project_id', 'assignee_id', 'title', 'description', 'status', 'priority', 'due_date'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'position' => 'integer',
            'completed_at' => 'datetime',
            'due_soon_notified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TaskComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    /**
     * Tasks the user may see: everything in projects visible to them.
     * Mirrors TaskPolicy::view().
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->whereIn('project_id', Project::query()->visibleTo($user)->select('id'));
    }

    /** Tasks whose project is not archived. */
    #[Scope]
    protected function inOpenProjects(Builder $query): void
    {
        $query->whereHas('project', fn (Builder $p) => $p->whereNull('archived_at'));
    }

    /**
     * The overdue rule as raw SQL for SUM(CASE WHEN … ) aggregates.
     * Must stay identical to scopeOverdue() / isOverdue().
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function overdueSql(string $table = 'tasks'): array
    {
        return [
            "{$table}.status != ? AND {$table}.due_date < ?",
            [TaskStatus::Completed->value, today()->toDateString()],
        ];
    }

    /** Not completed and due before today. */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', '!=', TaskStatus::Completed)
            ->whereDate('due_date', '<', today());
    }

    /** Not completed and due today. */
    #[Scope]
    protected function dueToday(Builder $query): void
    {
        $query->where('status', '!=', TaskStatus::Completed)
            ->whereDate('due_date', today());
    }

    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Completed
            && $this->due_date !== null
            && $this->due_date->lt(today());
    }
}
