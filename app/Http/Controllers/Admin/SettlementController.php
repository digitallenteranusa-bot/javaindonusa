<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class SettlementController extends Controller
{
    /**
     * Display settlement list
     */
    public function index(Request $request)
    {
        $query = Settlement::with(['collector:id,name', 'receivedBy:id,name']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by collector
        if ($request->filled('collector_id')) {
            $query->where('collector_id', $request->collector_id);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('settlement_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('settlement_date', '<=', $request->end_date);
        }

        $query->orderBy('created_at', 'desc');

        $settlements = $query->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Stats
        $pendingCount = Settlement::where('status', 'pending')->count();
        $pendingAmount = Settlement::where('status', 'pending')->sum('settlement_amount');

        return Inertia::render('Admin/Settlement/Index', [
            'settlements' => $settlements,
            'filters' => $request->only(['status', 'collector_id', 'start_date', 'end_date']),
            'collectors' => User::where('role', 'penagih')->get(['id', 'name']),
            'pendingCount' => $pendingCount,
            'pendingAmount' => $pendingAmount,
        ]);
    }

    /**
     * Show pending settlements for quick verification
     */
    public function pending()
    {
        $settlements = Settlement::with(['collector:id,name'])
            ->where('status', 'pending')
            ->orderBy('settlement_date', 'asc')
            ->get();

        return Inertia::render('Admin/Settlement/Pending', [
            'settlements' => $settlements,
            'totalAmount' => $settlements->sum('settlement_amount'),
        ]);
    }

    /**
     * Show settlement detail
     */
    public function show(Settlement $settlement)
    {
        $settlement->load(['collector', 'receivedBy']);

        // Get related payments (collected on that date by that collector)
        $payments = Payment::where('collector_id', $settlement->collector_id)
            ->whereDate('created_at', $settlement->settlement_date)
            ->with('customer:id,customer_id,name')
            ->get();

        // Get related expenses
        $expenses = Expense::where('user_id', $settlement->collector_id)
            ->whereDate('expense_date', $settlement->settlement_date)
            ->where('status', 'approved')
            ->get();

        return Inertia::render('Admin/Settlement/Show', [
            'settlement' => $settlement,
            'payments' => $payments,
            'expenses' => $expenses,
        ]);
    }

    /**
     * Verify settlement
     */
    public function verify(Request $request, Settlement $settlement)
    {
        if ($settlement->status !== 'pending') {
            return back()->with('error', 'Setoran ini sudah diverifikasi');
        }

        $validated = $request->validate([
            'actual_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $difference = $validated['actual_amount'] - $settlement->settlement_amount;

        $settlement->update([
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'actual_amount' => $validated['actual_amount'],
            'difference' => $difference,
            'verification_notes' => $validated['notes'] ?? null,
        ]);

        $message = 'Setoran berhasil diverifikasi';
        if ($difference != 0) {
            $diffLabel = $difference > 0 ? 'lebih' : 'kurang';
            $message .= ". Selisih Rp " . number_format(abs($difference), 0, ',', '.') . " ({$diffLabel})";
        }

        return back()->with('success', $message);
    }

    /**
     * Reject settlement
     */
    public function reject(Request $request, Settlement $settlement)
    {
        if ($settlement->status !== 'pending') {
            return back()->with('error', 'Setoran ini sudah diproses');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $settlement->update([
            'status' => 'rejected',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'verification_notes' => 'Ditolak: ' . $validated['reason'],
        ]);

        return back()->with('success', 'Setoran berhasil ditolak');
    }

    /**
     * Get collector balance (unverified collections)
     */
    public function collectorBalance(Request $request)
    {
        $collectors = User::where('role', 'penagih')
            ->where('is_active', true)
            ->get();

        $balances = [];

        foreach ($collectors as $collector) {
            // Total collected but not yet settled
            $unsettledPayments = Payment::where('collector_id', $collector->id)
                ->where('payment_method', 'cash')
                ->whereDoesntHave('settlement')
                ->sum('amount');

            // Approved expenses not yet deducted
            $unsettledExpenses = Expense::where('user_id', $collector->id)
                ->where('status', 'approved')
                ->whereDoesntHave('settlement')
                ->sum('amount');

            $balances[] = [
                'collector' => $collector->name,
                'collector_id' => $collector->id,
                'unsettled_collections' => $unsettledPayments,
                'unsettled_expenses' => $unsettledExpenses,
                'expected_balance' => $unsettledPayments - $unsettledExpenses,
            ];
        }

        return response()->json($balances);
    }

    /**
     * Daily settlement summary
     */
    public function dailySummary(Request $request)
    {
        $date = $request->get('date', today()->toDateString());

        $settlements = Settlement::with('user:id,name')
            ->whereDate('settlement_date', $date)
            ->get();

        $summary = [
            'date' => $date,
            'total_expected' => $settlements->sum('settlement_amount'),
            'total_actual' => $settlements->sum('actual_amount'),
            'total_difference' => $settlements->sum('difference'),
            'verified_count' => $settlements->where('status', 'verified')->count(),
            'pending_count' => $settlements->where('status', 'pending')->count(),
        ];

        return Inertia::render('Admin/Settlement/DailySummary', [
            'settlements' => $settlements,
            'summary' => $summary,
        ]);
    }
}
