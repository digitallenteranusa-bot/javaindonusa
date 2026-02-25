<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Area;
use App\Models\User;
use App\Exports\UnpaidCustomersExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class UnpaidMonitoringController extends Controller
{
    /**
     * Display unpaid customers monitoring dashboard
     */
    public function index(Request $request)
    {
        $periodMonth = $request->filled('period_month') ? (int) $request->period_month : now()->month;
        $periodYear = $request->filled('period_year') ? (int) $request->period_year : now()->year;

        $query = Invoice::with([
            'customer:id,customer_id,name,phone,address,package_id,area_id,collector_id',
            'customer.package:id,name,price',
            'customer.area:id,name',
            'customer.collector:id,name',
        ])
            ->where('period_month', $periodMonth)
            ->where('period_year', $periodYear)
            ->whereIn('status', ['pending', 'partial', 'overdue']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_id', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by area
        if ($request->filled('area_id')) {
            $query->whereHas('customer', fn($q) => $q->where('area_id', $request->area_id));
        }

        // Filter by collector
        if ($request->filled('collector_id')) {
            $query->whereHas('customer', fn($q) => $q->where('collector_id', $request->collector_id));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $query->orderBy('status', 'desc') // overdue first
            ->orderBy('remaining_amount', 'desc');

        $invoices = $query->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Add overdue_months per customer
        $invoices->getCollection()->transform(function ($invoice) use ($periodMonth, $periodYear) {
            $invoice->overdue_months = Invoice::where('customer_id', $invoice->customer_id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->where(function ($q) use ($periodMonth, $periodYear) {
                    $q->where('period_year', '<', $periodYear)
                        ->orWhere(function ($q2) use ($periodMonth, $periodYear) {
                            $q2->where('period_year', $periodYear)
                                ->where('period_month', '<=', $periodMonth);
                        });
                })
                ->count();
            return $invoice;
        });

        // Summary stats for selected period (unpaid only)
        $baseQuery = Invoice::where('period_month', $periodMonth)
            ->where('period_year', $periodYear)
            ->whereIn('status', ['pending', 'partial', 'overdue']);

        $stats = [
            'unpaid_count' => (clone $baseQuery)->count(),
            'overdue_count' => (clone $baseQuery)->where('status', 'overdue')->count(),
            'total_billed' => (clone $baseQuery)->sum('total_amount'),
            'total_paid' => (clone $baseQuery)->sum('paid_amount'),
            'total_remaining' => (clone $baseQuery)->sum('remaining_amount'),
        ];

        // Filter options
        $areas = Area::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $collectors = User::where('role', 'penagih')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Billing/UnpaidMonitoring', [
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'area_id', 'collector_id', 'status', 'period_month', 'period_year']),
            'stats' => $stats,
            'areas' => $areas,
            'collectors' => $collectors,
            'periodMonth' => $periodMonth,
            'periodYear' => $periodYear,
            'years' => range(now()->year, now()->year - 2),
            'months' => collect(range(1, 12))->map(fn($m) => [
                'value' => $m,
                'label' => Carbon::create()->month($m)->translatedFormat('F'),
            ]),
        ]);
    }

    /**
     * Export unpaid customers to Excel
     */
    public function export(Request $request)
    {
        $periodMonth = $request->filled('period_month') ? (int) $request->period_month : now()->month;
        $periodYear = $request->filled('period_year') ? (int) $request->period_year : now()->year;

        $filename = "belum_bayar_{$periodYear}-{$periodMonth}_" . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new UnpaidCustomersExport(
                $periodMonth,
                $periodYear,
                $request->get('area_id'),
                $request->get('collector_id'),
                $request->get('status'),
                $request->get('search'),
            ),
            $filename
        );
    }
}
