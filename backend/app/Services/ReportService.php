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
 * Date range: projects and tasks *created* within [from, to] (inclusive
 * days). Overdue is evaluated against today.
 */
class ReportService
{
    /** @return array<string, mixed> */
    public function build(User $user, ?Carbon $from, ?Carbon $to): array
    {
        $projects = $this->inRange(Project::query()->visibleTo($user)->notArchived(), $from, $to);
        $tasks = $this->inRange(
            Task::query()->visibleTo($user)->whereHas('project', fn ($q) => $q->whereNull('archived_at')),
            $from,
            $to,
        );

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
                'rate' => $total === 0 ? 0 : (int) round($completed / $total * 100),
            ],
            'overdue' => [
                'count' => (clone $tasks)->overdue()->count(),
                'tasks' => (clone $tasks)->overdue()
                    ->with(['project:id,name,manager_id', 'assignee'])
                    ->orderBy('due_date')
                    ->limit(20)
                    ->get(),
            ],
            'members' => $this->memberSummary(clone $tasks),
        ];
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
     * Per-assignee totals in one grouped query (no N+1), most assigned first.
     *
     * @return list<array<string, mixed>>
     */
    private function memberSummary(Builder $tasks): array
    {
        $completed = TaskStatus::Completed->value;
        $today = today()->toDateString();

        $rows = $tasks->toBase()
            ->join('users', 'users.id', '=', 'tasks.assignee_id')
            ->select('users.id', 'users.name')
            ->selectRaw('COUNT(*) AS assigned')
            ->selectRaw('SUM(CASE WHEN tasks.status = ? THEN 1 ELSE 0 END) AS completed', [$completed])
            ->selectRaw('SUM(CASE WHEN tasks.status != ? AND tasks.due_date < ? THEN 1 ELSE 0 END) AS overdue', [$completed, $today])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('assigned')
            ->orderBy('users.name')
            ->get();

        return $rows->map(fn ($r) => [
            'user_id' => (int) $r->id,
            'name' => $r->name,
            'assigned' => (int) $r->assigned,
            'completed' => (int) $r->completed,
            'pending' => (int) $r->assigned - (int) $r->completed,
            'overdue' => (int) $r->overdue,
            'completion_rate' => (int) round($r->completed / $r->assigned * 100),
        ])->values()->all();
    }
}
