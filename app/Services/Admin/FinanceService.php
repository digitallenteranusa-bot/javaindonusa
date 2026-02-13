<?php

namespace App\Services\Admin;

use App\Models\Expense;
use App\Models\OperationalExpense;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    // ================================================================
    // DASHBOARD STATS
    // ================================================================

    /**
     * Ambil ringkasan statistik keuangan untuk bulan/tahun tertentu
     */
    public function getDashboardStats(int $month, int $year): array
    {
        // Pendapatan kotor (dari pembayaran verified)
        $revenue = Payment::verified()
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('amount');

        // Pengeluaran collector (expense approved)
        $collectorExpense = Expense::approved()
            ->whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year)
            ->sum('amount');

        // Pengeluaran operasional (non-salary)
        $operationalExpense = OperationalExpense::nonSalary()
            ->byMonth($month, $year)
            ->sum('amount');

        // Gaji pegawai
        $salary = OperationalExpense::salaries()
            ->byMonth($month, $year)
            ->sum('amount');

        // Total belanja = collector + operasional + gaji
        $totalExpense = $collectorExpense + $operationalExpense + $salary;

        // Laba bersih
        $netProfit = $revenue - $totalExpense;

        return [
            'revenue' => (float) $revenue,
            'collector_expense' => (float) $collectorExpense,
            'operational_expense' => (float) $operationalExpense,
            'salary' => (float) $salary,
            'total_expense' => (float) $totalExpense,
            'net_profit' => (float) $netProfit,
        ];
    }

    // ================================================================
    // MONTHLY TREND (12 Bulan)
    // ================================================================

    /**
     * Ambil trend keuangan 12 bulan dalam satu tahun
     */
    public function getMonthlyTrend(int $year): array
    {
        $months = [];

        for ($m = 1; $m <= 12; $m++) {
            $monthLabel = Carbon::create($year, $m, 1)->translatedFormat('M');

            $revenue = Payment::verified()
                ->whereMonth('created_at', $m)
                ->whereYear('created_at', $year)
                ->sum('amount');

            $collectorExp = Expense::approved()
                ->whereMonth('expense_date', $m)
                ->whereYear('expense_date', $year)
                ->sum('amount');

            $opExp = OperationalExpense::nonSalary()
                ->byMonth($m, $year)
                ->sum('amount');

            $salary = OperationalExpense::salaries()
                ->byMonth($m, $year)
                ->sum('amount');

            $totalExpense = $collectorExp + $opExp + $salary;

            $months[] = [
                'month' => $monthLabel,
                'month_number' => $m,
                'revenue' => (float) $revenue,
                'expense' => (float) $totalExpense,
                'profit' => (float) ($revenue - $totalExpense),
            ];
        }

        return $months;
    }

    // ================================================================
    // EXPENSE BREAKDOWN
    // ================================================================

    /**
     * Breakdown pengeluaran per kategori untuk bulan/tahun tertentu
     */
    public function getExpenseBreakdown(int $month, int $year): array
    {
        // Collector expense total
        $collectorTotal = Expense::approved()
            ->whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year)
            ->sum('amount');

        // Operational expenses by category
        $operationalByCategory = OperationalExpense::byMonth($month, $year)
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $breakdown = [];

        // Add collector expense as a category
        $breakdown[] = [
            'category' => 'collector',
            'label' => 'Pengeluaran Collector',
            'total' => (float) $collectorTotal,
            'count' => Expense::approved()
                ->whereMonth('expense_date', $month)
                ->whereYear('expense_date', $year)
                ->count(),
        ];

        // Add each operational category
        $categories = OperationalExpense::getCategories();
        foreach ($categories as $key => $label) {
            $data = $operationalByCategory->get($key);
            $breakdown[] = [
                'category' => $key,
                'label' => $label,
                'total' => (float) ($data?->total ?? 0),
                'count' => (int) ($data?->count ?? 0),
            ];
        }

        return $breakdown;
    }
}
