<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** GET /api/dashboard — everything the dashboard page needs in one request. */
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                ...$dashboard->summary($user),
                'project_progress' => ProjectResource::collection($dashboard->projectProgress($user)),
                'upcoming_tasks' => TaskResource::collection($dashboard->upcomingTasks($user)),
                'recent_activity' => ActivityLogResource::collection($dashboard->recentActivity($user)),
            ],
        ]);
    }
}
