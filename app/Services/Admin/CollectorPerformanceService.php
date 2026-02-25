<?php

namespace App\Services\Admin;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;

class CollectorPerformanceService
{
    /**
     * Get performance data (income + expenses) per collector.
     */
    public function getPerformanceData(?int $month = null, ?int $year = null): array
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $collectors = User::where('role', 'penagih')
            ->where('is_active', true)
            ->withCount(['assignedCustomers' => function ($q) {
                $q->where('status', 'active');
            }])
            ->get();

        $performance = [];
        $totalBillableAll = 0;
        $totalCollectedAll = 0;
        $totalCashAll = 0;
        $totalTransferAll = 0;
        $totalExpenseAll = 0;

        foreach ($collectors as $collector) {
            $payments = Payment::where('collector_id', $collector->id)
                ->where('status', 'verified')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $totalBillable = (float) Invoice::whereHas('customer', function ($q) use ($collector) {
                    $q->where('collector_id', $collector->id);
                })
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total_amount');

            $totalCollected = (float) $payments->sum('amount');
            $cashCollected = (float) $payments->where('payment_method', 'cash')->sum('amount');
            $transferCollected = (float) $payments->where('payment_method', 'transfer')->sum('amount');

            $totalExpense = (float) Expense::where('user_id', $collector->id)
                ->where('status', 'approved')
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->sum('amount');

            $performance[] = [
                'id' => $collector->id,
                'name' => $collector->name,
                'customers_count' => $collector->assigned_customers_count,
                'total_billable' => $totalBillable,
                'total_collected' => $totalCollected,
                'cash_collected' => $cashCollected,
                'transfer_collected' => $transferCollected,
                'transactions' => $payments->count(),
                'collection_rate' => $totalBillable > 0
                    ? round(($totalCollected / $totalBillable) * 100, 1)
                    : 0,
                'total_expense' => $totalExpense,
                'net_income' => $totalCollected - $totalExpense,
            ];

            $totalBillableAll += $totalBillable;
            $totalCollectedAll += $totalCollected;
            $totalCashAll += $cashCollected;
            $totalTransferAll += $transferCollected;
            $totalExpenseAll += $totalExpense;
        }

        usort($performance, fn($a, $b) => $b['collection_rate'] <=> $a['collection_rate']);

        return [
            'collectors' => $performance,
            'summary' => [
                'total_collectors' => count($performance),
                'total_billable' => $totalBillableAll,
                'total_collected' => $totalCollectedAll,
                'total_cash' => $totalCashAll,
                'total_transfer' => $totalTransferAll,
                'total_expense' => $totalExpenseAll,
            ],
        ];
    }

    /**
     * Get available years from invoices.
     */
    public function getAvailableYears(): array
    {
        return Invoice::selectRaw('DISTINCT period_year')
            ->orderByDesc('period_year')
            ->pluck('period_year')
            ->toArray();
    }

    /**
     * Get month options in Indonesian.
     */
    public function getMonthOptions(): array
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return collect($months)->map(fn($label, $value) => [
            'value' => $value,
            'label' => $label,
        ])->values()->toArray();
    }
}
