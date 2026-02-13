<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationalExpense;
use App\Services\Admin\FinanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function __construct(
        protected FinanceService $financeService
    ) {}

    // ================================================================
    // DASHBOARD KEUANGAN
    // ================================================================

    /**
     * Halaman utama dashboard keuangan
     */
    public function dashboard(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $stats = $this->financeService->getDashboardStats($month, $year);
        $trend = $this->financeService->getMonthlyTrend($year);
        $breakdown = $this->financeService->getExpenseBreakdown($month, $year);

        return Inertia::render('Admin/Finance/Dashboard', [
            'stats' => $stats,
            'trend' => $trend,
            'breakdown' => $breakdown,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }

    // ================================================================
    // CRUD PENGELUARAN OPERASIONAL
    // ================================================================

    /**
     * Daftar pengeluaran operasional
     */
    public function expenses(Request $request)
    {
        $query = OperationalExpense::with('createdBy:id,name');

        // Filter by month/year
        if ($request->filled('month') && $request->filled('year')) {
            $query->byMonth((int) $request->month, (int) $request->year);
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $expenses = $query->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Summary for current filter
        $summaryQuery = OperationalExpense::query();
        if ($request->filled('month') && $request->filled('year')) {
            $summaryQuery->byMonth((int) $request->month, (int) $request->year);
        }
        $totalAmount = $summaryQuery->sum('amount');
        $totalCount = $summaryQuery->count();

        return Inertia::render('Admin/Finance/Expenses', [
            'expenses' => $expenses,
            'filters' => $request->only(['month', 'year', 'category', 'search']),
            'categories' => OperationalExpense::getCategories(),
            'totalAmount' => (float) $totalAmount,
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * Form tambah pengeluaran
     */
    public function createExpense()
    {
        return Inertia::render('Admin/Finance/ExpenseForm', [
            'categories' => OperationalExpense::getCategories(),
            'expense' => null,
        ]);
    }

    /**
     * Simpan pengeluaran baru
     */
    public function storeExpense(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|in:salary,rent,electricity,internet,equipment,maintenance,other',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'receipt_photo' => 'nullable|image|max:5120',
            'notes' => 'nullable|string|max:1000',
        ]);

        $expenseDate = Carbon::parse($validated['expense_date']);

        // Handle file upload
        $receiptPath = null;
        if ($request->hasFile('receipt_photo')) {
            $file = $request->file('receipt_photo');
            $receiptPath = $file->store('operational-receipts/' . now()->format('Y/m'), 'public');
        }

        OperationalExpense::create([
            'category' => $validated['category'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'expense_date' => $expenseDate,
            'period_month' => $expenseDate->month,
            'period_year' => $expenseDate->year,
            'receipt_photo' => $receiptPath,
            'notes' => $validated['notes'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('admin.finance.expenses')
            ->with('success', 'Pengeluaran operasional berhasil ditambahkan');
    }

    /**
     * Form edit pengeluaran
     */
    public function editExpense(OperationalExpense $expense)
    {
        return Inertia::render('Admin/Finance/ExpenseForm', [
            'categories' => OperationalExpense::getCategories(),
            'expense' => $expense,
        ]);
    }

    /**
     * Update pengeluaran
     */
    public function updateExpense(Request $request, OperationalExpense $expense)
    {
        $validated = $request->validate([
            'category' => 'required|in:salary,rent,electricity,internet,equipment,maintenance,other',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'receipt_photo' => 'nullable|image|max:5120',
            'notes' => 'nullable|string|max:1000',
        ]);

        $expenseDate = Carbon::parse($validated['expense_date']);

        // Handle file upload
        if ($request->hasFile('receipt_photo')) {
            // Delete old receipt
            if ($expense->receipt_photo) {
                Storage::disk('public')->delete($expense->receipt_photo);
            }
            $file = $request->file('receipt_photo');
            $validated['receipt_photo'] = $file->store('operational-receipts/' . now()->format('Y/m'), 'public');
        }

        $expense->update([
            'category' => $validated['category'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'expense_date' => $expenseDate,
            'period_month' => $expenseDate->month,
            'period_year' => $expenseDate->year,
            'receipt_photo' => $validated['receipt_photo'] ?? $expense->receipt_photo,
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('admin.finance.expenses')
            ->with('success', 'Pengeluaran operasional berhasil diperbarui');
    }

    /**
     * Hapus pengeluaran
     */
    public function destroyExpense(OperationalExpense $expense)
    {
        // Delete receipt file
        if ($expense->receipt_photo) {
            Storage::disk('public')->delete($expense->receipt_photo);
        }

        $expense->delete();

        return back()->with('success', 'Pengeluaran operasional berhasil dihapus');
    }
}
