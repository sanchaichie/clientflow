<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** GET /api/dashboard — everything the dashboard page needs in one request. */
    public function __invoke(Request $request, DashboardService $dashboard): DashboardResource
    {
        $user = $request->user();

        return new DashboardResource([
            ...$dashboard->summary($user),
            'project_progress' => $dashboard->projectProgress($user),
            'upcoming_tasks' => $dashboard->upcomingTasks($user),
            'recent_activity' => $dashboard->recentActivity($user),
        ]);
    }
}
