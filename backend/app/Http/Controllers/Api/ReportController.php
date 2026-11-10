<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Http\Resources\ReportResource;
use App\Services\ReportService;

class ReportController extends Controller
{
    /** GET /api/reports — admins and project managers (spec §3). */
    public function __invoke(ReportRequest $request, ReportService $reports): ReportResource
    {
        return new ReportResource($reports->build($request->user(), $request->from(), $request->to()));
    }
}
