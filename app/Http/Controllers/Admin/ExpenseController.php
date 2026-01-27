<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    /**
     * Display expense list
     */
    public function index(Request $request)
    {
        $query = Expense::with(['user:id,name', 'verifiedBy:id,name']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by user/collector
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('expense_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('expense_date', '<=', $request->end_date);
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Search
        if ($request->filled('search')) {
            $query->where('description', 'like', "%{$request->search}%");
        }

        $query->orderBy('created_at', 'desc');

        $expenses = $query->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Stats
        $pendingCount = Expense::where('status', 'pending')->count();
        $pendingAmount = Expense::where('status', 'pending')->sum('amount');

        return Inertia::render('Admin/Expense/Index', [
            'expenses' => $expenses,
            'filters' => $request->only(['status', 'user_id', 'start_date', 'end_date', 'category', 'search']),
            'collectors' => User::where('role', 'penagih')->get(['id', 'name']),
            'pendingCount' => $pendingCount,
            'pendingAmount' => $pendingAmount,
            'categories' => [
                'transport' => 'Transportasi',
                'meal' => 'Makan',
                'phone' => 'Pulsa/Telepon',
                'maintenance' => 'Perawatan',
                'other' => 'Lainnya',
            ],
        ]);
    }

    /**
     * Show pending expenses for quick approval
     */
    public function pending()
    {
        $expenses = Expense::with(['user:id,name'])
            ->where('status', 'pending')
            ->orderBy('expense_date', 'asc')
            ->get();

        return Inertia::render('Admin/Expense/Pending', [
            'expenses' => $expenses,
            'totalAmount' => $expenses->sum('amount'),
        ]);
    }

    /**
     * Show expense detail
     */
    public function show(Expense $expense)
    {
        $expense->load(['user', 'verifiedBy']);

        return Inertia::render('Admin/Expense/Show', [
            'expense' => $expense,
        ]);
    }

    /**
     * Approve expense
     */
    public function approve(Request $request, Expense $expense)
    {
        if ($expense->status !== 'pending') {
            return back()->with('error', 'Pengeluaran ini sudah diproses');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $expense->update([
            'status' => 'approved',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Pengeluaran berhasil disetujui');
    }

    /**
     * Reject expense
     */
    public function reject(Request $request, Expense $expense)
    {
        if ($expense->status !== 'pending') {
            return back()->with('error', 'Pengeluaran ini sudah diproses');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $expense->update([
            'status' => 'rejected',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'rejection_reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Pengeluaran berhasil ditolak');
    }

    /**
     * Bulk approve expenses
     */
    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'expense_ids' => 'required|array',
            'expense_ids.*' => 'exists:expenses,id',
        ]);

        $updated = Expense::whereIn('id', $validated['expense_ids'])
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);

        return back()->with('success', "{$updated} pengeluaran berhasil disetujui");
    }

    /**
     * Get expense summary by collector
     */
    public function collectorSummary(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());

        $summary = Expense::with('user:id,name')
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw('user_id, SUM(amount) as total_amount, COUNT(*) as total_count')
            ->groupBy('user_id')
            ->get()
            ->map(fn($item) => [
                'user' => $item->user->name,
                'total_amount' => $item->total_amount,
                'total_count' => $item->total_count,
            ]);

        return response()->json([
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => $summary,
            'grand_total' => $summary->sum('total_amount'),
        ]);
    }
}
