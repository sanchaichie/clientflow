<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape of GET /api/reports. Wraps the array built by ReportService.
 *
 * @property array<string, mixed> $resource
 */
class ReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'range' => $r['range'],
            'projects_by_status' => $r['projects_by_status'],
            'tasks_by_status' => $r['tasks_by_status'],
            'completion' => $r['completion'],
            'overdue' => [
                'count' => $r['overdue']['count'],
                'tasks' => TaskResource::collection($r['overdue']['tasks']),
            ],
            'members' => $r['members'],
            'unassigned' => $r['unassigned'],
        ];
    }
}
