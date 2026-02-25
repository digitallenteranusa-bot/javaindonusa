<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\CollectorPerformanceSummaryExport;
use App\Exports\CollectorDetailExport;
use App\Services\Admin\CollectorPerformanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class CollectorPerformanceController extends Controller
{
    protected CollectorPerformanceService $service;

    public function __construct(CollectorPerformanceService $service)
    {
        $this->service = $service;
    }

    /**
     * Display collector performance dashboard.
     */
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $data = $this->service->getPerformanceData($month, $year);

        return Inertia::render('Admin/Finance/CollectorPerformance', [
            'filters' => [
                'year' => $year,
                'month' => $month,
            ],
            'years' => $this->service->getAvailableYears(),
            'months' => $this->service->getMonthOptions(),
            'collectors' => $data['collectors'],
            'summary' => $data['summary'],
        ]);
    }

    /**
     * Export all collectors summary to Excel.
     */
    public function export(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $fileName = "Performa_Penagih_{$monthNames[$month]}_{$year}.xlsx";

        return Excel::download(new CollectorPerformanceSummaryExport($month, $year), $fileName);
    }

    /**
     * Export individual collector detail to Excel (multi-sheet).
     */
    public function exportCollector(Request $request, int $collector)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $collectorUser = \App\Models\User::findOrFail($collector);
        $safeName = str_replace(' ', '_', $collectorUser->name);
        $fileName = "Detail_Penagih_{$safeName}_{$monthNames[$month]}_{$year}.xlsx";

        return Excel::download(new CollectorDetailExport($collector, $month, $year), $fileName);
    }
}
