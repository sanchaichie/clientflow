<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape of GET /api/dashboard. Wraps the pieces built by DashboardService.
 *
 * @property array<string, mixed> $resource
 */
class DashboardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'stats' => $r['stats'],
            'scope' => $r['scope'],
            'project_progress' => ProjectResource::collection($r['project_progress']),
            'upcoming_tasks' => TaskResource::collection($r['upcoming_tasks']),
            'recent_activity' => ActivityLogResource::collection($r['recent_activity']),
        ];
    }
}
