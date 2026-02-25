<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\VpsMonitoringService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class VpsMonitoringController extends Controller
{
    protected VpsMonitoringService $service;

    public function __construct(VpsMonitoringService $service)
    {
        $this->service = $service;
    }

    public function index(): Response
    {
        $data = $this->service->getVpsHealthData();

        return Inertia::render('Admin/Analytics/VpsMonitoring', $data);
    }

    public function refresh(): JsonResponse
    {
        return response()->json($this->service->getVpsHealthData());
    }
}
