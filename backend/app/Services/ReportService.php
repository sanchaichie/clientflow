<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reports (spec §4.8), built from grouped aggregate queries.
 *
 * Scope: everything the user can see (admins: all; PMs: projects they manage
 * or belong to), excluding archived projects.
 *
 * - Status breakdowns, completion and per-member assigned/completed cover
 *   projects and tasks *created* within [from, to] (whole days in the app
 *   timezone, APP_TIMEZONE).
 * - Overdue figures are a snapshot of what is overdue *now*, regardless of
 *   when the task was created — an old overdue task is still overdue.
 */
class ReportService
{
    /** @return array<string, mixed> */
    public function build(User $user, ?Carbon $from, ?Carbon $to): array
    {
        $projects = $this->inRange(Project::query()->visibleTo($user)->notArchived(), $from, $to);
        $tasks = $this->inRange($this->taskScope($user), $from, $to);
        $overdue = $this->taskScope($user)->overdue();

        $tasksByStatus = $this->countBy(clone $tasks, 'status', TaskStatus::cases());
        $completed = $tasksByStatus[TaskStatus::Completed->value];
        $total = array_sum($tasksByStatus);

        return [
            'range' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'projects_by_status' => $this->countBy(clone $projects, 'status', ProjectStatus::cases()),
            'tasks_by_status' => $tasksByStatus,
            'completion' => [
                'total' => $total,
                'completed' => $completed,
                'pending' => $total - $completed,
                'rate' => $this->rate($completed, $total),
            ],
            'overdue' => [
                'count' => (clone $overdue)->count(),
                'tasks' => (clone $overdue)
                    ->with(['project:id,name,manager_id', 'assignee'])
                    ->orderBy('due_date')
                    ->limit(20)
                    ->get(),
            ],
            ...$this->memberSummary(clone $tasks, clone $overdue),
        ];
    }

    /** Same visibility + open-project rule as the dashboard (shared scopes). */
    private function taskScope(User $user): Builder
    {
        return Task::query()->visibleTo($user)->inOpenProjects();
    }

    private function inRange(Builder $query, ?Carbon $from, ?Carbon $to): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->when($from, fn ($q) => $q->where("{$table}.created_at", '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where("{$table}.created_at", '<=', $to->copy()->endOfDay()));
    }

    /**
     * Count rows per enum value, including zeros, in enum order.
     *
     * @param  list<\BackedEnum>  $cases
     * @return array<string, int>
     */
    private function countBy(Builder $query, string $column, array $cases): array
    {
        $counts = $query->toBase()
            ->select($column, DB::raw('COUNT(*) AS total'))
            ->groupBy($column)
            ->pluck('total', $column);

        $result = [];
        foreach ($cases as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    /**
     * Per-assignee figures in three queries whatever the team size:
     * assigned/completed in range, overdue now, and the names.
     * Unassigned tasks are reported separately so the totals add up.
     *
     * @return array{members: list<array<string, mixed>>, unassigned: array<string, int>}
     */
    private function memberSummary(Builder $rangeTasks, Builder $overdueTasks): array
    {
        $inRange = $rangeTasks->toBase()
            ->select('tasks.assignee_id')
            ->selectRaw('COUNT(*) AS assigned')
            ->selectRaw('SUM(CASE WHEN tasks.status = ? THEN 1 ELSE 0 END) AS completed', [TaskStatus::Completed->value])
            ->groupBy('tasks.assignee_id')
            ->get()
            ->keyBy(fn ($r) => (int) $r->assignee_id); // null → 0 = unassigned

        $overdue = $overdueTasks->toBase()
            ->select('tasks.assignee_id')
            ->selectRaw('COUNT(*) AS overdue')
            ->groupBy('tasks.assignee_id')
            ->pluck('overdue', 'assignee_id');

        $row = function (int $id) use ($inRange, $overdue): array {
            $assigned = (int) ($inRange[$id]->assigned ?? 0);
            $completed = (int) ($inRange[$id]->completed ?? 0);

            return [
                'assigned' => $assigned,
                'completed' => $completed,
                'pending' => $assigned - $completed,
                // pluck() stores the NULL assignee under the key '' (unassigned).
                'overdue' => (int) ($overdue[$id ?: ''] ?? 0),
                'completion_rate' => $this->rate($completed, $assigned),
            ];
        };

        $userIds = $inRange->keys()
            ->merge($overdue->keys()->map(fn ($id) => (int) $id))
            ->filter()
            ->unique();
        $names = User::query()->whereKey($userIds)->pluck('name', 'id');

        $members = $userIds
            ->map(fn (int $id) => ['user_id' => $id, 'name' => $names[$id] ?? 'Unknown user', ...$row($id)])
            ->sortBy([['assigned', 'desc'], ['name', 'asc']])
            ->values()
            ->all();

        return ['members' => $members, 'unassigned' => $row(0)];
    }

    private function rate(int $done, int $total): int
    {
        return $total === 0 ? 0 : (int) round($done / $total * 100);
    }
}
