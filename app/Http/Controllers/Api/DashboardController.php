<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFiltersRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function show(
        ReportFiltersRequest $request,
        DashboardService $dashboard,
    ): JsonResponse {
        return response()->json($dashboard->build(
            $request->user(),
            $request->filters(),
            $request->hasCustomDateRange(),
        ));
    }
}
