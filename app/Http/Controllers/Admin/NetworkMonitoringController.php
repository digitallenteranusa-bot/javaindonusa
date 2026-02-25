<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\NetworkMonitoringService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class NetworkMonitoringController extends Controller
{
    protected NetworkMonitoringService $service;

    public function __construct(NetworkMonitoringService $service)
    {
        $this->service = $service;
    }

    public function index(): Response
    {
        $data = $this->service->getNetworkHealthData();

        return Inertia::render('Admin/Analytics/NetworkMonitoring', $data);
    }

    public function refresh(): JsonResponse
    {
        return response()->json($this->service->getNetworkHealthData());
    }
}
