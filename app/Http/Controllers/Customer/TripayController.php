<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TripayTransaction;
use App\Services\Payment\TripayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TripayController extends Controller
{
    protected TripayService $tripayService;

    public function __construct(TripayService $tripayService)
    {
        $this->tripayService = $tripayService;
    }

    /**
     * Get available payment channels
     */
    public function getChannels()
    {
        $channels = $this->tripayService->getPaymentChannels();

        return response()->json([
            'success' => true,
            'data' => $channels,
        ]);
    }

    /**
     * Show payment page
     */
    public function payPage()
    {
        $customer = $this->getAuthenticatedCustomer();

        if (!$customer) {
            return redirect()->route('customer.login');
        }

        if (!$this->tripayService->isEnabled()) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'Pembayaran online belum tersedia');
        }

        // Get unpaid invoices
        $unpaidInvoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get()
            ->map(fn(Invoice $inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'period' => $inv->period_month . '/' . $inv->period_year,
                'period_label' => $this->getMonthName($inv->period_month) . ' ' . $inv->period_year,
                'total_amount' => (float) $inv->total_amount,
                'paid_amount' => (float) $inv->paid_amount,
                'remaining_amount' => (float) $inv->remaining_amount,
                'status' => $inv->status,
                'due_date' => $inv->due_date?->format('Y-m-d'),
            ]);

        // Get active Tripay transaction for this customer (if any)
        $activeTransaction = TripayTransaction::where('customer_id', $customer->id)
            ->where('status', TripayTransaction::STATUS_UNPAID)
            ->where('expired_at', '>', now())
            ->latest()
            ->first();

        return Inertia::render('Customer/Pay', [
            'customer' => $customer,
            'unpaidInvoices' => $unpaidInvoices,
            'activeTransaction' => $activeTransaction ? [
                'id' => $activeTransaction->id,
                'reference' => $activeTransaction->reference,
                'method' => $activeTransaction->method,
                'amount' => (float) $activeTransaction->amount,
                'total_amount' => (float) $activeTransaction->total_amount,
                'fee_customer' => (float) $activeTransaction->fee_customer,
                'status' => $activeTransaction->status,
                'checkout_url' => $activeTransaction->checkout_url,
                'qr_url' => $activeTransaction->qr_url,
                'pay_url' => $activeTransaction->pay_url,
                'expired_at' => $activeTransaction->expired_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * Create a new payment transaction
     */
    public function createTransaction(Request $request)
    {
        $customer = $this->getAuthenticatedCustomer();

        if (!$customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!$this->tripayService->isEnabled()) {
            return response()->json(['message' => 'Pembayaran online belum tersedia'], 400);
        }

        $request->validate([
            'method' => 'required|string',
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'integer|exists:invoices,id',
        ]);

        // Verify all invoices belong to this customer
        $invoiceCount = Invoice::where('customer_id', $customer->id)
            ->whereIn('id', $request->invoice_ids)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->count();

        if ($invoiceCount !== count($request->invoice_ids)) {
            return response()->json(['message' => 'Invoice tidak valid'], 400);
        }

        // Check for existing unpaid transaction
        $existing = TripayTransaction::where('customer_id', $customer->id)
            ->where('status', TripayTransaction::STATUS_UNPAID)
            ->where('expired_at', '>', now())
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Masih ada transaksi yang belum dibayar. Selesaikan atau tunggu kadaluarsa.',
                'transaction' => [
                    'id' => $existing->id,
                    'reference' => $existing->reference,
                    'method' => $existing->method,
                    'amount' => (float) $existing->amount,
                    'total_amount' => (float) $existing->total_amount,
                    'checkout_url' => $existing->checkout_url,
                    'qr_url' => $existing->qr_url,
                    'pay_url' => $existing->pay_url,
                    'expired_at' => $existing->expired_at?->toIso8601String(),
                ],
            ], 409);
        }

        try {
            $transaction = $this->tripayService->createTransaction(
                $customer,
                $request->invoice_ids,
                $request->method
            );

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibuat',
                'transaction' => [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'method' => $transaction->method,
                    'amount' => (float) $transaction->amount,
                    'total_amount' => (float) $transaction->total_amount,
                    'fee_customer' => (float) $transaction->fee_customer,
                    'status' => $transaction->status,
                    'checkout_url' => $transaction->checkout_url,
                    'qr_url' => $transaction->qr_url,
                    'pay_url' => $transaction->pay_url,
                    'expired_at' => $transaction->expired_at?->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle callback from Tripay (webhook, no auth)
     */
    public function callback(Request $request)
    {
        $callbackJson = $request->getContent();

        // Verify signature
        if (!$this->tripayService->verifySignature($callbackJson)) {
            Log::warning('Tripay callback: Invalid signature');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        $data = json_decode($callbackJson, true);

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        try {
            $this->tripayService->handleCallback($data);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Tripay callback error', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return response()->json(['success' => false, 'message' => 'Processing error'], 500);
        }
    }

    /**
     * Check transaction status
     */
    public function checkStatus(TripayTransaction $transaction)
    {
        $customer = $this->getAuthenticatedCustomer();

        if (!$customer || $transaction->customer_id !== $customer->id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check if expired locally
        if ($transaction->is_expired) {
            $transaction->update(['status' => TripayTransaction::STATUS_EXPIRED]);
            return response()->json([
                'success' => true,
                'status' => TripayTransaction::STATUS_EXPIRED,
            ]);
        }

        // Check with Tripay API
        $result = $this->tripayService->checkTransactionStatus($transaction);

        return response()->json([
            'success' => true,
            'status' => $transaction->fresh()->status,
        ]);
    }

    /**
     * Get authenticated customer from session
     */
    protected function getAuthenticatedCustomer(): ?Customer
    {
        $customerId = session('customer_id');
        $token = session('customer_token');

        if (!$customerId || !$token) {
            return null;
        }

        return Customer::find($customerId);
    }

    /**
     * Get Indonesian month name
     */
    protected function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$month] ?? '';
    }
}
