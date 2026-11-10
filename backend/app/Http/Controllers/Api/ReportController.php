<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Http\Resources\TaskResource;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /** GET /api/reports — admins and project managers (spec §3). */
    public function __invoke(ReportRequest $request, ReportService $reports): JsonResponse
    {
        $data = $reports->build($request->user(), $request->from(), $request->to());
        $data['overdue']['tasks'] = TaskResource::collection($data['overdue']['tasks']);

        return response()->json(['data' => $data]);
    }
}
