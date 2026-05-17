<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Exports\CustomerDebtByCollectorExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class CustomerDebtReportController extends Controller
{
    public function index(Request $request)
    {
        $periodMonth = (int) $request->get('period_month', now()->month);
        $periodYear = (int) $request->get('period_year', now()->year);
        $collectorId = $request->get('collector_id');

        $collectors = User::where('role', 'penagih')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $data = $this->getDebtData($periodMonth, $periodYear, $collectorId);

        return Inertia::render('Admin/Collector/CustomerDebt', [
            'filters' => [
                'period_month' => $periodMonth,
                'period_year' => $periodYear,
                'collector_id' => $collectorId,
            ],
            'collectors' => $collectors,
            'debtByCollector' => $data['by_collector'],
            'summary' => $data['summary'],
            'years' => range(now()->year, now()->year - 3),
            'months' => collect(range(1, 12))->map(fn($m) => [
                'value' => $m,
                'label' => Carbon::create()->month($m)->translatedFormat('F'),
            ]),
        ]);
    }

    public function export(Request $request)
    {
        $periodMonth = (int) $request->get('period_month', now()->month);
        $periodYear = (int) $request->get('period_year', now()->year);
        $collectorId = $request->get('collector_id');

        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $fileName = "Piutang_Pelanggan_{$monthNames[$periodMonth]}_{$periodYear}.xlsx";

        return Excel::download(
            new CustomerDebtByCollectorExport($periodMonth, $periodYear, $collectorId),
            $fileName
        );
    }

    protected function getDebtData(int $month, int $year, ?string $collectorId = null): array
    {
        $query = Invoice::with([
            'customer:id,customer_id,name,phone,address,package_id,area_id,collector_id,total_debt',
            'customer.package:id,name,price',
            'customer.area:id,name',
            'customer.collector:id,name',
        ])
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where(function ($q) use ($month, $year) {
                $q->where('period_year', '<', $year)
                    ->orWhere(function ($q2) use ($month, $year) {
                        $q2->where('period_year', $year)
                            ->where('period_month', '<=', $month);
                    });
            });

        if ($collectorId) {
            $query->whereHas('customer', fn($q) => $q->where('collector_id', $collectorId));
        }

        $invoices = $query->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        $grouped = $invoices->groupBy(fn($inv) => $inv->customer?->collector?->name ?? 'Tanpa Penagih');

        $byCollector = [];
        $totalDebt = 0;
        $totalCustomers = 0;

        foreach ($grouped as $collectorName => $collectorInvoices) {
            $customerGroups = $collectorInvoices->groupBy('customer_id');

            $customers = [];
            foreach ($customerGroups as $customerId => $custInvoices) {
                $customer = $custInvoices->first()->customer;
                $totalRemaining = $custInvoices->sum('remaining_amount');
                $unpaidMonths = $custInvoices->count();

                $customers[] = [
                    'customer_id' => $customer?->customer_id,
                    'name' => $customer?->name,
                    'phone' => $customer?->phone,
                    'area' => $customer?->area?->name ?? '-',
                    'package' => $customer?->package?->name ?? '-',
                    'total_debt' => $totalRemaining,
                    'unpaid_months' => $unpaidMonths,
                    'oldest_period' => $custInvoices->first()->period_month . '/' . $custInvoices->first()->period_year,
                ];

                $totalDebt += $totalRemaining;
            }

            usort($customers, fn($a, $b) => $b['total_debt'] <=> $a['total_debt']);

            $collectorTotal = array_sum(array_column($customers, 'total_debt'));
            $totalCustomers += count($customers);

            $byCollector[] = [
                'collector_name' => $collectorName,
                'customers' => $customers,
                'total_debt' => $collectorTotal,
                'customer_count' => count($customers),
            ];
        }

        usort($byCollector, fn($a, $b) => $b['total_debt'] <=> $a['total_debt']);

        return [
            'by_collector' => $byCollector,
            'summary' => [
                'total_debt' => $totalDebt,
                'total_customers' => $totalCustomers,
                'total_collectors' => count($byCollector),
            ],
        ];
    }
}
