<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Role-appropriate dashboard data (spec §4.2), in a handful of queries:
 * admins/PMs see figures for everything they can access; team members see
 * figures for the tasks assigned to them. Archived records are excluded.
 */
class DashboardService
{
    /** @return array<string, mixed> */
    public function summary(User $user): array
    {
        return [
            'stats' => [
                // Team members have no client access (DECISIONS 010).
                'total_clients' => $user->isTeamMember()
                    ? null
                    : Client::query()->visibleTo($user)->notArchived()->count(),
                ...$this->projectStats($user),
                ...$this->taskStats($user),
            ],
            'scope' => $user->isTeamMember() ? 'my_tasks' : 'all_visible',
        ];
    }

    /** Active/planning/on-hold projects, soonest deadline first. */
    public function projectProgress(User $user, int $limit = 6): Collection
    {
        return Project::query()
            ->visibleTo($user)
            ->notArchived()
            ->whereIn('status', [ProjectStatus::Planning, ProjectStatus::Active, ProjectStatus::OnHold])
            ->with('client:id,company_name')
            ->orderByRaw('end_date IS NULL, end_date')
            ->limit($limit)
            ->get();
    }

    /** Open tasks with a due date, most urgent first. */
    public function upcomingTasks(User $user, int $limit = 6): Collection
    {
        return $this->taskScope($user)
            ->where('status', '!=', TaskStatus::Completed)
            ->whereNotNull('due_date')
            ->with(['project:id,name,manager_id', 'assignee'])
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }

    /** Latest activity in projects the user can see (filled from Milestone 6). */
    public function recentActivity(User $user, int $limit = 8): Collection
    {
        return ActivityLog::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->whereIn(
                'project_id', Project::query()->visibleTo($user)->select('id')
            ))
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /** @return array<string, int> */
    private function projectStats(User $user): array
    {
        $row = Project::query()
            ->visibleTo($user)
            ->notArchived()
            ->toBase()
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS active', [ProjectStatus::Active->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [ProjectStatus::Completed->value])
            ->first();

        return [
            'active_projects' => (int) $row->active,
            'completed_projects' => (int) $row->completed,
        ];
    }

    /** @return array<string, int> */
    private function taskStats(User $user): array
    {
        $today = today()->toDateString();
        $completed = TaskStatus::Completed->value;

        $row = $this->taskScope($user)
            ->toBase()
            ->selectRaw('SUM(CASE WHEN status != ? THEN 1 ELSE 0 END) AS pending', [$completed])
            ->selectRaw('SUM(CASE WHEN status != ? AND due_date < ? THEN 1 ELSE 0 END) AS overdue', [$completed, $today])
            ->selectRaw('SUM(CASE WHEN status != ? AND due_date = ? THEN 1 ELSE 0 END) AS due_today', [$completed, $today])
            ->first();

        return [
            'pending_tasks' => (int) $row->pending,
            'overdue_tasks' => (int) $row->overdue,
            'due_today_tasks' => (int) $row->due_today,
        ];
    }

    /** Tasks counted for this user: their own for team members, all visible otherwise. */
    private function taskScope(User $user): Builder
    {
        return Task::query()
            ->visibleTo($user)
            ->whereHas('project', fn ($q) => $q->whereNull('archived_at'))
            ->when($user->isTeamMember(), fn ($q) => $q->where('assignee_id', $user->id));
    }
}
