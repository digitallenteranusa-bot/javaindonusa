<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\User;
use App\Services\Billing\DebtIsolationService;
use App\Services\PdfService;
use App\Exports\PaymentExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class PaymentController extends Controller
{
    protected DebtIsolationService $billingService;

    public function __construct(DebtIsolationService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Display payment list
     */
    public function index(Request $request)
    {
        $query = Payment::with([
            'customer:id,customer_id,name,phone',
            'collector:id,name',
            'receivedBy:id,name',
        ]);

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Filter by payment method
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by collector
        if ($request->filled('collector_id')) {
            $query->where('collector_id', $request->collector_id);
        }

        // Filter by status (if applicable)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_id', 'like', "%{$search}%");
                    });
            });
        }

        // Sort
        $query->orderBy('created_at', 'desc');

        $payments = $query->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Get summary stats for the filtered period
        $statsQuery = Payment::query();
        if ($request->filled('start_date')) {
            $statsQuery->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $statsQuery->whereDate('created_at', '<=', $request->end_date);
        }

        // Only count non-cancelled payments for amount stats
        $validStatsQuery = (clone $statsQuery)->where('status', '!=', 'cancelled');

        $stats = [
            'total_amount' => $validStatsQuery->sum('amount'),
            'total_count' => $validStatsQuery->count(),
            'cash' => (clone $validStatsQuery)->where('payment_method', 'cash')->sum('amount'),
            'transfer' => (clone $validStatsQuery)->where('payment_method', 'transfer')->sum('amount'),
            'cancelled_count' => (clone $statsQuery)->where('status', 'cancelled')->count(),
        ];

        // Get collectors for filter dropdown
        $collectors = User::where('role', 'penagih')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Payment/Index', [
            'payments' => $payments,
            'filters' => $request->only(['start_date', 'end_date', 'payment_method', 'collector_id', 'search', 'status']),
            'stats' => $stats,
            'collectors' => $collectors,
        ]);
    }

    /**
     * Show payment detail
     */
    public function show(Payment $payment)
    {
        $payment->load([
            'customer',
            'collector',
            'receivedBy',
            'invoices',
        ]);

        return Inertia::render('Admin/Payment/Show', [
            'payment' => $payment,
        ]);
    }

    /**
     * Show manual payment form
     */
    public function create()
    {
        return Inertia::render('Admin/Payment/Form', [
            'customers' => Customer::whereIn('status', ['active', 'isolated'])
                ->where('total_debt', '>', 0)
                ->with('package:id,name,price')
                ->get(['id', 'customer_id', 'name', 'phone', 'total_debt']),
        ]);
    }

    /**
     * Store manual payment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:1000',
            'payment_method' => 'required|in:cash,transfer',
            'transfer_proof' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        $result = $this->billingService->processPayment(
            $customer,
            $validated['amount'],
            $validated['payment_method'],
            null, // No collector for admin payment
            $validated['transfer_proof'] ?? null,
            $validated['notes'] ?? null
        );

        if ($result['success']) {
            return redirect()->route('admin.payments.show', $result['payment'])
                ->with('success', 'Pembayaran berhasil dicatat');
        }

        return back()->with('error', 'Gagal memproses pembayaran');
    }

    /**
     * Cancel payment (reverse)
     */
    public function cancel(Request $request, Payment $payment)
    {
        // Only recent payments can be cancelled (within 24 hours)
        if ($payment->created_at->lt(now()->subDay())) {
            return back()->with('error', 'Pembayaran lebih dari 24 jam tidak dapat dibatalkan');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // This is a complex operation that should:
        // 1. Reverse invoice payments
        // 2. Restore customer debt
        // 3. Update payment status
        // For now, just mark as cancelled

        $payment->update([
            'status' => 'cancelled',
            'notes' => ($payment->notes ?? '') . "\nDibatalkan: " . $validated['reason'],
        ]);

        // Recalculate customer debt
        $payment->customer->recalculateTotalDebt();

        return back()->with('success', 'Pembayaran berhasil dibatalkan');
    }

    /**
     * Daily payment summary
     */
    public function dailySummary(Request $request)
    {
        $date = $request->get('date', today()->toDateString());

        $payments = Payment::with(['customer:id,customer_id,name', 'collector:id,name'])
            ->whereDate('created_at', $date)
            ->orderBy('created_at', 'desc')
            ->get();

        $summary = [
            'date' => $date,
            'total_amount' => $payments->sum('amount'),
            'total_count' => $payments->count(),
            'by_method' => $payments->groupBy('payment_method')->map(fn($p) => [
                'count' => $p->count(),
                'amount' => $p->sum('amount'),
            ]),
            'by_collector' => $payments->groupBy('collector_id')->map(fn($p) => [
                'collector' => $p->first()->collector?->name ?? 'Admin',
                'count' => $p->count(),
                'amount' => $p->sum('amount'),
            ])->values(),
        ];

        return Inertia::render('Admin/Payment/DailySummary', [
            'payments' => $payments,
            'summary' => $summary,
        ]);
    }

    /**
     * Export payments to Excel
     */
    public function export(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $paymentMethod = $request->get('payment_method');
        $collectorId = $request->filled('collector_id') ? (int) $request->collector_id : null;

        $filename = 'payments';
        if ($startDate && $endDate) {
            $filename .= "_{$startDate}_to_{$endDate}";
        } elseif ($startDate) {
            $filename .= "_from_{$startDate}";
        }
        $filename .= '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new PaymentExport($startDate, $endDate, $paymentMethod, $collectorId),
            $filename
        );
    }

    /**
     * Download payment receipt as PDF
     */
    public function downloadPdf(Payment $payment, PdfService $pdfService)
    {
        $pdf = $pdfService->generatePaymentReceiptPdf($payment);

        return $pdf->download("receipt_{$payment->payment_number}.pdf");
    }

    /**
     * Stream payment receipt PDF (for preview)
     */
    public function streamPdf(Payment $payment, PdfService $pdfService)
    {
        $pdf = $pdfService->generatePaymentReceiptPdf($payment);

        return $pdf->stream("receipt_{$payment->payment_number}.pdf");
    }
}
