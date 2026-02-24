<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RevenueAnalyticsController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = $request->get('month') ? (int) $request->get('month') : null;

        $startDate = $month
            ? \Carbon\Carbon::create($year, $month, 1)->startOfMonth()->format('Y-m-d')
            : \Carbon\Carbon::create($year, 1, 1)->format('Y-m-d');
        $endDate = $month
            ? \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d')
            : \Carbon\Carbon::create($year, 12, 31)->format('Y-m-d');

        return Inertia::render('Admin/Analytics/RevenueAnalytics', [
            'filters' => [
                'year' => $year,
                'month' => $month,
            ],
            'years' => $this->reportService->getAvailableYears(),
            'months' => $this->getMonthOptions(),
            'summary' => $this->reportService->getYearlyRevenueSummary($year),
            'yearComparison' => $this->reportService->getYearOverYearComparison($year),
            'monthlyTrend' => $this->reportService->getMonthlyRevenueTrend($year),
            'growthRate' => $this->reportService->getMonthlyGrowthRate($year),
            'collectionRateTrend' => $this->reportService->getCollectionRateTrend($year),
            'revenueByPackage' => $this->reportService->getRevenueByPackage($year, $month),
            'revenueByArea' => $this->reportService->getRevenueByArea($year, $month),
            'paymentMethods' => $this->reportService->getPaymentByMethod($startDate, $endDate),
            'customerGrowth' => $this->reportService->getCustomerGrowthTrend($year),
            'customerMetrics' => $this->reportService->getCustomerMetrics($year),
            'topCustomers' => $this->reportService->getTopDebtors(10),
            'debtAging' => $this->reportService->getDebtAging(),
        ]);
    }

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
