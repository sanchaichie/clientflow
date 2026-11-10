<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// "progress" is intentionally not fillable: it is derived from tasks.
#[Fillable(['client_id', 'manager_id', 'name', 'description', 'start_date', 'end_date', 'status'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'progress' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withTimestamps();
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<ActivityLog, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    #[Scope]
    protected function notArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Projects the user may see: admins see all; everyone else sees projects
     * they manage or are a member of. Mirrors ProjectPolicy::view().
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('manager_id', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m->whereKey($user->id));
        });
    }

    public function isManagedBy(User $user): bool
    {
        return $this->manager_id === $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->relationLoaded('members')
            ? $this->members->contains($user)
            : $this->members()->whereKey($user->id)->exists();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Recompute the cached progress percentage from this project's tasks
     * (completed / total, rounded) with one aggregate query, and save it.
     */
    public function recalculateProgress(): int
    {
        $counts = $this->tasks()
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [TaskStatus::Completed->value])
            ->first();

        $total = (int) $counts->total;
        $progress = $total === 0 ? 0 : (int) round(((int) $counts->completed / $total) * 100);

        $this->forceFill(['progress' => $progress])->saveQuietly();

        return $progress;
    }
}
