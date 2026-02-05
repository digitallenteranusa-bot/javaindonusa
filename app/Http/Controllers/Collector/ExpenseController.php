<?php

namespace App\Http\Controllers\Collector;

use App\Http\Controllers\Controller;
use App\Services\Collector\ExpenseService;
use App\Services\Collector\CollectorService;
use App\Models\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    protected ExpenseService $expenseService;
    protected CollectorService $collectorService;

    public function __construct(
        ExpenseService $expenseService,
        CollectorService $collectorService
    ) {
        $this->expenseService = $expenseService;
        $this->collectorService = $collectorService;
    }

    /**
     * Daftar pengeluaran penagih
     */
    public function index(Request $request)
    {
        $collector = auth()->user();

        $startDate = $request->get('start_date')
            ? Carbon::parse($request->get('start_date'))
            : Carbon::now()->startOfMonth();

        $endDate = $request->get('end_date')
            ? Carbon::parse($request->get('end_date'))
            : Carbon::now()->endOfMonth();

        $expenses = $this->expenseService->getExpenseHistory(
            $collector,
            $startDate,
            $endDate
        );

        $summary = $this->expenseService->getMonthlyExpenseSummary($collector);

        // Settlement info
        $settlement = $this->collectorService->calculateFinalSettlement(
            $collector,
            $startDate,
            $endDate
        );

        return Inertia::render('Collector/Expenses', [
            'expenses' => $expenses,
            'summary' => $summary,
            'settlement' => $settlement,
            'dailySummary' => [
                'pending' => $summary['pending_total'] ?? 0,
                'approved' => $summary['approved_total'] ?? 0,
                'rejected' => $summary['rejected_total'] ?? 0,
            ],
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * Tambah pengeluaran
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'description' => 'required|string|max:255',
            'receipt_photo' => 'nullable|image|max:5120', // Max 5MB
            'expense_date' => 'nullable|date',
        ]);

        $collector = auth()->user();

        try {
            // Upload foto nota jika ada
            $receiptPath = null;
            if ($request->hasFile('receipt_photo')) {
                $receiptPath = $this->expenseService->uploadReceipt(
                    $collector,
                    $request->file('receipt_photo')
                );
            }

            $expense = $this->expenseService->createExpense(
                $collector,
                $request->amount,
                'other', // Default category
                $request->description,
                $receiptPath,
                $request->expense_date ? Carbon::parse($request->expense_date) : null
            );

            return back()->with('success', 'Pengeluaran berhasil dicatat');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Ringkasan setoran
     */
    public function settlement(Request $request)
    {
        $collector = auth()->user();

        $date = $request->get('date')
            ? Carbon::parse($request->get('date'))
            : Carbon::today();

        // Ringkasan harian
        $dailySummary = $this->collectorService->getDailySummary($collector, $date);

        // Histori settlement (paginated)
        $settlements = $this->expenseService->getSettlementHistory($collector);

        // Pending settlement - SEMUA yang belum disetor (bukan hanya hari ini)
        $pendingSettlement = $this->collectorService->calculateUnsettledAmount($collector);

        return Inertia::render('Collector/Settlement', [
            'dailySummary' => $dailySummary,
            'settlements' => $settlements,
            'pendingSettlement' => $pendingSettlement,
            'date' => $date->toDateString(),
        ]);
    }

    /**
     * Request settlement (setor ke kantor)
     */
    public function requestSettlement(Request $request)
    {
        $request->validate([
            'actual_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $collector = auth()->user();

        try {
            // Ambil periode dari unsettled calculation
            $unsettled = $this->collectorService->calculateUnsettledAmount($collector);

            if ($unsettled['must_settle'] <= 0) {
                return back()->with('error', 'Tidak ada yang perlu disetor.');
            }

            $settlement = $this->expenseService->createSettlementFromUnsettled(
                $collector,
                Carbon::parse($unsettled['period']['start']),
                Carbon::parse($unsettled['period']['end']),
                $unsettled,
                $request->actual_amount,
                $request->notes
            );

            $formattedAmount = 'Rp ' . number_format($settlement->actual_amount, 0, ',', '.');

            return back()->with('success', "Request setoran {$formattedAmount} berhasil dibuat. Menunggu verifikasi admin.");

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
