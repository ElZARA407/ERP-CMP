<?php
// app/Http/Controllers/Api/Kpi/DashboardController.php

namespace App\Http\Controllers\Api\Kpi;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends BaseApiController
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function index(): JsonResponse
    {
        return $this->success($this->dashboardService->overview());
    }

    public function production(): JsonResponse
    {
        return $this->success($this->dashboardService->productionKpi());
    }

    public function stock(): JsonResponse
    {
        return $this->success($this->dashboardService->stockKpi());
    }

    public function commercial(): JsonResponse
    {
        return $this->success($this->dashboardService->commercialKpi());
    }

    public function finance(): JsonResponse
    {
        return $this->success($this->dashboardService->financeKpi());
    }

    public function pilotage(): JsonResponse
    {
        return $this->success($this->dashboardService->pilotage());
    }
}