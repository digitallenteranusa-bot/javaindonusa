<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\XenditTransaction;
use App\Services\Payment\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XenditController extends Controller
{
    protected XenditService $xenditService;

    public function __construct(XenditService $xenditService)
    {
        $this->xenditService = $xenditService;
    }

    /**
     * Get available payment channels
     */
    public function getChannels()
    {
        $channels = $this->xenditService->getPaymentChannels();

        return response()->json([
            'success' => true,
            'data' => $channels,
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

        if (!$this->xenditService->isEnabled()) {
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

        // Check for existing pending transaction
        $existing = XenditTransaction::where('customer_id', $customer->id)
            ->where('status', XenditTransaction::STATUS_PENDING)
            ->where('expired_at', '>', now())
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Masih ada transaksi yang belum dibayar. Selesaikan atau tunggu kadaluarsa.',
                'transaction' => [
                    'id' => $existing->id,
                    'external_id' => $existing->external_id,
                    'method' => $existing->method,
                    'amount' => (float) $existing->amount,
                    'status' => $existing->status,
                    'invoice_url' => $existing->invoice_url,
                    'expired_at' => $existing->expired_at?->toIso8601String(),
                ],
            ], 409);
        }

        try {
            $transaction = $this->xenditService->createTransaction(
                $customer,
                $request->invoice_ids,
                $request->method
            );

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibuat',
                'transaction' => [
                    'id' => $transaction->id,
                    'external_id' => $transaction->external_id,
                    'method' => $transaction->method,
                    'amount' => (float) $transaction->amount,
                    'status' => $transaction->status,
                    'invoice_url' => $transaction->invoice_url,
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
     * Handle callback from Xendit (webhook, no auth)
     */
    public function callback(Request $request)
    {
        $callbackToken = $request->header('X-Callback-Token', '');

        // Verify webhook token
        if (!$this->xenditService->verifyWebhookToken($callbackToken)) {
            Log::warning('Xendit callback: Invalid webhook token');
            return response()->json(['success' => false, 'message' => 'Invalid token'], 403);
        }

        $data = $request->all();

        if (empty($data)) {
            return response()->json(['success' => false, 'message' => 'Invalid data'], 400);
        }

        try {
            $this->xenditService->handleCallback($data);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Xendit callback error', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return response()->json(['success' => false, 'message' => 'Processing error'], 500);
        }
    }

    /**
     * Check transaction status
     */
    public function checkStatus(XenditTransaction $transaction)
    {
        $customer = $this->getAuthenticatedCustomer();

        if (!$customer || $transaction->customer_id !== $customer->id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check if expired locally
        if ($transaction->is_expired) {
            $transaction->update(['status' => XenditTransaction::STATUS_EXPIRED]);
            return response()->json([
                'success' => true,
                'status' => XenditTransaction::STATUS_EXPIRED,
            ]);
        }

        // Check with Xendit API
        $this->xenditService->checkTransactionStatus($transaction);

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
}
