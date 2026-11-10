<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListActivityRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    /**
     * GET /api/activity?project_id=&task_id=  — newest first.
     * Non-admins only see activity of projects they manage or belong to.
     */
    public function index(ListActivityRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $filters = $request->validated();

        $logs = ActivityLog::query()
            ->with('user')
            ->when(! $user->isAdmin(), fn ($q) => $q->whereIn(
                'project_id', Project::query()->visibleTo($user)->select('id')
            ))
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id))
            ->when($filters['task_id'] ?? null, fn ($q, $id) => $q
                ->where('subject_type', (new Task)->getMorphClass())
                ->where('subject_id', $id))
            ->latest()
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return ActivityLogResource::collection($logs);
    }
}
