<?php

namespace App\Http\Controllers\Collector;

use App\Http\Controllers\Controller;
use App\Services\Collector\CollectorService;
use App\Services\Collector\ExpenseService;
use App\Models\User;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\CollectionLog;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected CollectorService $collectorService;
    protected ExpenseService $expenseService;

    public function __construct(
        CollectorService $collectorService,
        ExpenseService $expenseService
    ) {
        $this->collectorService = $collectorService;
        $this->expenseService = $expenseService;
    }

    /**
     * Generate laporan harian penagih (PDF)
     */
    public function dailyReport(Request $request)
    {
        $collector = auth()->user();
        $date = $request->get('date')
            ? Carbon::parse($request->get('date'))
            : Carbon::today();

        $data = $this->prepareDailyReportData($collector, $date);

        $pdf = Pdf::loadView('pdf.collector.daily-report', $data);

        return $pdf->download("laporan-harian-{$collector->name}-{$date->format('Y-m-d')}.pdf");
    }

    /**
     * Generate laporan bulanan penagih (PDF)
     */
    public function monthlyReport(Request $request)
    {
        $collector = auth()->user();
        $month = $request->get('month')
            ? Carbon::parse($request->get('month') . '-01')
            : Carbon::now()->startOfMonth();

        $data = $this->prepareMonthlyReportData($collector, $month);

        $pdf = Pdf::loadView('pdf.collector.monthly-report', $data);

        return $pdf->download("laporan-bulanan-{$collector->name}-{$month->format('Y-m')}.pdf");
    }

    /**
     * Generate laporan setoran penagih (PDF)
     */
    public function settlementReport(Request $request)
    {
        $collector = auth()->user();

        $startDate = Carbon::parse($request->get('start_date', Carbon::today()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::today()));

        $data = $this->prepareSettlementReportData($collector, $startDate, $endDate);

        $pdf = Pdf::loadView('pdf.collector.settlement-report', $data);

        return $pdf->download("laporan-setoran-{$collector->name}-{$startDate->format('Y-m-d')}.pdf");
    }

    /**
     * Preview laporan untuk print (HTML)
     */
    public function printPreview(Request $request)
    {
        $collector = auth()->user();
        $type = $request->get('type', 'daily');
        $date = $request->get('date')
            ? Carbon::parse($request->get('date'))
            : Carbon::today();

        $data = match ($type) {
            'daily' => $this->prepareDailyReportData($collector, $date),
            'monthly' => $this->prepareMonthlyReportData($collector, $date->startOfMonth()),
            'settlement' => $this->prepareSettlementReportData($collector, $date, $date),
            default => $this->prepareDailyReportData($collector, $date),
        };

        $data['printMode'] = true;

        return view("pdf.collector.{$type}-report", $data);
    }

    // ================================================================
    // DATA PREPARATION
    // ================================================================

    protected function prepareDailyReportData(User $collector, Carbon $date): array
    {
        // Pembayaran hari ini
        $payments = Payment::where('collector_id', $collector->id)
            ->whereDate('created_at', $date)
            ->with('customer:id,customer_id,name,address')
            ->orderBy('created_at')
            ->get();

        // Pengeluaran hari ini
        $expenses = Expense::where('user_id', $collector->id)
            ->whereDate('expense_date', $date)
            ->get();

        // Kunjungan hari ini
        $visits = CollectionLog::where('collector_id', $collector->id)
            ->whereDate('created_at', $date)
            ->with('customer:id,customer_id,name')
            ->get();

        // Settlement
        $settlement = $this->collectorService->calculateFinalSettlement($collector, $date, $date);

        return [
            'collector' => $collector,
            'date' => $date,
            'payments' => $payments,
            'expenses' => $expenses,
            'visits' => $visits,
            'settlement' => $settlement,
            'totals' => [
                'payment_count' => $payments->count(),
                'payment_cash' => $payments->where('payment_method', 'cash')->sum('amount'),
                'payment_transfer' => $payments->where('payment_method', 'transfer')->sum('amount'),
                'payment_total' => $payments->sum('amount'),
                'expense_count' => $expenses->count(),
                'expense_total' => $expenses->sum('amount'),
                'visit_count' => $visits->count(),
            ],
            'company' => \App\Models\IspInfo::first(),
            'generated_at' => now(),
        ];
    }

    protected function prepareMonthlyReportData(User $collector, Carbon $month): array
    {
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();

        // Pembayaran bulan ini
        $payments = Payment::where('collector_id', $collector->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('customer:id,customer_id,name')
            ->get();

        // Pengeluaran bulan ini
        $expenses = Expense::where('user_id', $collector->id)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // Statistik harian
        $dailyStats = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dayPayments = $payments->filter(function ($p) use ($date) {
                return Carbon::parse($p->created_at)->isSameDay($date);
            });

            $dayExpenses = $expenses->filter(function ($e) use ($date) {
                return Carbon::parse($e->expense_date)->isSameDay($date);
            });

            if ($dayPayments->count() > 0 || $dayExpenses->count() > 0) {
                $dailyStats[] = [
                    'date' => $date->copy(),
                    'payment_count' => $dayPayments->count(),
                    'payment_total' => $dayPayments->sum('amount'),
                    'expense_total' => $dayExpenses->sum('amount'),
                    'net' => $dayPayments->sum('amount') - $dayExpenses->sum('amount'),
                ];
            }
        }

        // Settlement
        $settlement = $this->collectorService->calculateFinalSettlement(
            $collector,
            $startDate,
            $endDate
        );

        // Expense summary
        $expenseSummary = $this->expenseService->getMonthlyExpenseSummary($collector, $month);

        return [
            'collector' => $collector,
            'month' => $month,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'payments' => $payments,
            'expenses' => $expenses,
            'dailyStats' => $dailyStats,
            'settlement' => $settlement,
            'expenseSummary' => $expenseSummary,
            'totals' => [
                'payment_count' => $payments->count(),
                'payment_total' => $payments->sum('amount'),
                'expense_count' => $expenses->count(),
                'expense_total' => $expenses->sum('amount'),
                'net_collection' => $payments->sum('amount') - $expenses->sum('amount'),
            ],
            'company' => \App\Models\IspInfo::first(),
            'generated_at' => now(),
        ];
    }

    protected function prepareSettlementReportData(
        User $collector,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        // Pembayaran dalam periode
        $payments = Payment::where('collector_id', $collector->id)
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->with('customer:id,customer_id,name,address')
            ->orderBy('created_at')
            ->get();

        // Pengeluaran dalam periode
        $expenses = Expense::where('user_id', $collector->id)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // Kalkulasi settlement
        $settlement = $this->collectorService->calculateFinalSettlement(
            $collector,
            $startDate,
            $endDate
        );

        return [
            'collector' => $collector,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'payments' => $payments,
            'expenses' => $expenses,
            'settlement' => $settlement,
            'summary' => [
                'total_customers_paid' => $payments->pluck('customer_id')->unique()->count(),
                'cash_collected' => $payments->where('payment_method', 'cash')->sum('amount'),
                'transfer_collected' => $payments->where('payment_method', 'transfer')->sum('amount'),
                'total_collected' => $payments->sum('amount'),
                'total_expense' => $expenses->where('status', '!=', 'rejected')->sum('amount'),
                'must_settle' => $settlement['must_settle'],
            ],
            'company' => \App\Models\IspInfo::first(),
            'generated_at' => now(),
        ];
    }
}
