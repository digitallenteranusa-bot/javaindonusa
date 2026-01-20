<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportService;
use App\Exports\CollectorReportExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Display report dashboard
     */
    public function index(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        return Inertia::render('Admin/Report/Index', [
            'filters' => [
                'year' => (int) $year,
                'month' => (int) $month,
            ],
            'years' => $this->reportService->getAvailableYears(),
            'months' => $this->getMonthOptions(),
            'revenue' => $this->reportService->getRevenueOverview($year, $month),
            'monthlyTrend' => $this->reportService->getMonthlyRevenueTrend($year),
            'paymentMethods' => $this->reportService->getPaymentByMethod(
                now()->startOfMonth()->format('Y-m-d'),
                now()->endOfMonth()->format('Y-m-d')
            ),
            'customerStatus' => $this->reportService->getCustomerStatusSummary(),
            'debtAging' => $this->reportService->getDebtAging(),
            'topDebtors' => $this->reportService->getTopDebtors(10),
        ]);
    }

    /**
     * Get collector performance report
     */
    public function collectorPerformance(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        return Inertia::render('Admin/Report/CollectorPerformance', [
            'filters' => [
                'year' => (int) $year,
                'month' => (int) $month,
            ],
            'years' => $this->reportService->getAvailableYears(),
            'months' => $this->getMonthOptions(),
            'collectors' => $this->reportService->getCollectorPerformance($month, $year),
        ]);
    }

    /**
     * Get area performance report
     */
    public function areaPerformance(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        return Inertia::render('Admin/Report/AreaPerformance', [
            'filters' => [
                'year' => (int) $year,
                'month' => (int) $month,
            ],
            'years' => $this->reportService->getAvailableYears(),
            'months' => $this->getMonthOptions(),
            'areas' => $this->reportService->getAreaPerformance($month, $year),
        ]);
    }

    /**
     * Get daily payment trend
     */
    public function dailyTrend(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        return response()->json([
            'trend' => $this->reportService->getDailyPaymentTrend($month, $year),
        ]);
    }

    /**
     * Export collector performance report to Excel
     */
    public function exportCollectorPerformance(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $fileName = "Laporan_Penagih_{$monthNames[$month]}_{$year}.xlsx";

        return Excel::download(new CollectorReportExport($month, $year), $fileName);
    }

    /**
     * Get month options for filter
     */
    protected function getMonthOptions(): array
    {
        return [
            ['value' => 1, 'label' => 'Januari'],
            ['value' => 2, 'label' => 'Februari'],
            ['value' => 3, 'label' => 'Maret'],
            ['value' => 4, 'label' => 'April'],
            ['value' => 5, 'label' => 'Mei'],
            ['value' => 6, 'label' => 'Juni'],
            ['value' => 7, 'label' => 'Juli'],
            ['value' => 8, 'label' => 'Agustus'],
            ['value' => 9, 'label' => 'September'],
            ['value' => 10, 'label' => 'Oktober'],
            ['value' => 11, 'label' => 'November'],
            ['value' => 12, 'label' => 'Desember'],
        ];
    }
}
