<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Display admin dashboard
     */
    public function index(Request $request)
    {
        $period = $request->get('period', 'this_month');
        $stats = $this->dashboardService->getDashboardStats($period);

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'period' => $period,
            'periods' => [
                'today' => 'Hari Ini',
                'yesterday' => 'Kemarin',
                'this_week' => 'Minggu Ini',
                'this_month' => 'Bulan Ini',
                'last_month' => 'Bulan Lalu',
                'this_year' => 'Tahun Ini',
            ],
        ]);
    }

    /**
     * Get dashboard stats (API)
     */
    public function stats(Request $request)
    {
        $period = $request->get('period', 'this_month');
        $stats = $this->dashboardService->getDashboardStats($period);

        return response()->json($stats);
    }

    /**
     * Get top paying customers
     */
    public function topPayingCustomers(Request $request)
    {
        $limit = $request->get('limit', 10);
        $data = $this->dashboardService->getTopPayingCustomers($limit);

        return response()->json($data);
    }

    /**
     * Get top debtors
     */
    public function topDebtors(Request $request)
    {
        $limit = $request->get('limit', 10);
        $data = $this->dashboardService->getTopDebtors($limit);

        return response()->json($data);
    }
}
