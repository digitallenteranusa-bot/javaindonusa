<?php

namespace App\Services\Payment;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\TripayTransaction;
use App\Services\Billing\PaymentService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TripayService
{
    protected PaymentService $paymentService;
    protected NotificationService $notificationService;

    public function __construct(PaymentService $paymentService, NotificationService $notificationService)
    {
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
    }

    /**
     * Check if Tripay is enabled (from DB settings or config)
     */
    public function isEnabled(): bool
    {
        $dbEnabled = Setting::getValue('tripay', 'enabled');
        if ($dbEnabled !== null) {
            return (bool) $dbEnabled;
        }

        return (bool) config('tripay.enabled');
    }

    /**
     * Get Tripay configuration (DB settings take priority over config file)
     */
    protected function getConfig(): array
    {
        $dbSettings = Setting::getGroup('tripay');

        $sandbox = $dbSettings['sandbox'] ?? config('tripay.sandbox', true);

        return [
            'api_key' => $dbSettings['api_key'] ?? config('tripay.api_key'),
            'private_key' => $dbSettings['private_key'] ?? config('tripay.private_key'),
            'merchant_code' => $dbSettings['merchant_code'] ?? config('tripay.merchant_code'),
            'sandbox' => (bool) $sandbox,
            'base_url' => $sandbox
                ? 'https://tripay.co.id/api-sandbox'
                : 'https://tripay.co.id/api',
        ];
    }

    /**
     * Get available payment channels from Tripay API
     */
    public function getPaymentChannels(): array
    {
        $config = $this->getConfig();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $config['api_key'],
            ])->get($config['base_url'] . '/merchant/payment-channel');

            if ($response->successful()) {
                $data = $response->json('data', []);

                // Filter only active channels and map to simpler structure
                return collect($data)
                    ->filter(fn($ch) => $ch['active'] ?? false)
                    ->map(fn($ch) => [
                        'code' => $ch['code'],
                        'name' => $ch['name'],
                        'group' => $ch['group'],
                        'icon_url' => $ch['icon_url'] ?? null,
                        'fee_merchant_flat' => $ch['fee_merchant']['flat'] ?? 0,
                        'fee_merchant_percent' => $ch['fee_merchant']['percent'] ?? 0,
                        'fee_customer_flat' => $ch['fee_customer']['flat'] ?? 0,
                        'fee_customer_percent' => $ch['fee_customer']['percent'] ?? 0,
                        'minimum_fee' => $ch['minimum_fee'] ?? 0,
                        'maximum_fee' => $ch['maximum_fee'] ?? 0,
                    ])
                    ->values()
                    ->toArray();
            }

            Log::error('Tripay: Failed to get payment channels', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Tripay: Exception getting payment channels', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Create a closed transaction on Tripay
     */
    public function createTransaction(Customer $customer, array $invoiceIds, string $method): TripayTransaction
    {
        $config = $this->getConfig();

        // Get unpaid invoices
        $invoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('id', $invoiceIds)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->get();

        if ($invoices->isEmpty()) {
            throw new \Exception('Tidak ada tagihan yang bisa dibayar');
        }

        $amount = (int) $invoices->sum('remaining_amount');
        $merchantRef = 'INV-' . now()->format('Ymd') . '-' . str_pad($customer->id, 5, '0', STR_PAD_LEFT) . '-' . time();

        $signature = $this->calculateSignature($config['merchant_code'], $merchantRef, $amount, $config['private_key']);

        // Build order items
        $orderItems = $invoices->map(fn(Invoice $inv) => [
            'name' => "Tagihan {$inv->period_month}/{$inv->period_year}",
            'price' => (int) $inv->remaining_amount,
            'quantity' => 1,
            'sku' => $inv->invoice_number,
        ])->toArray();

        $payload = [
            'method' => $method,
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email ?: 'noemail@javaindonusa.net',
            'customer_phone' => $customer->phone,
            'order_items' => $orderItems,
            'callback_url' => url('/api/tripay/callback'),
            'return_url' => url('/portal'),
            'expired_time' => (int) (now()->addHours(24)->timestamp),
            'signature' => $signature,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $config['api_key'],
            ])->post($config['base_url'] . '/transaction/create', $payload);

            if (!$response->successful()) {
                Log::error('Tripay: Failed to create transaction', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload' => array_merge($payload, ['signature' => '***']),
                ]);
                throw new \Exception('Gagal membuat transaksi: ' . ($response->json('message') ?? 'Unknown error'));
            }

            $data = $response->json('data');

            // Store the first invoice ID (for single invoice payments)
            $primaryInvoiceId = $invoices->count() === 1 ? $invoices->first()->id : null;

            $transaction = TripayTransaction::create([
                'customer_id' => $customer->id,
                'invoice_id' => $primaryInvoiceId,
                'reference' => $data['reference'],
                'merchant_ref' => $data['merchant_ref'],
                'method' => $method,
                'amount' => $amount,
                'fee_merchant' => $data['fee_merchant'] ?? 0,
                'fee_customer' => $data['fee_customer'] ?? 0,
                'total_amount' => $data['amount'],
                'status' => TripayTransaction::STATUS_UNPAID,
                'checkout_url' => $data['checkout_url'] ?? null,
                'qr_url' => $data['qr_url'] ?? null,
                'pay_url' => $data['pay_url'] ?? null,
                'expired_at' => isset($data['expired_time'])
                    ? \Carbon\Carbon::createFromTimestamp($data['expired_time'])
                    : now()->addHours(24),
            ]);

            return $transaction;
        } catch (\Exception $e) {
            if ($e->getMessage() !== 'Gagal membuat transaksi: Unknown error' && !str_starts_with($e->getMessage(), 'Gagal membuat transaksi:')) {
                Log::error('Tripay: Exception creating transaction', [
                    'error' => $e->getMessage(),
                ]);
            }
            throw $e;
        }
    }

    /**
     * Handle callback from Tripay webhook
     */
    public function handleCallback(array $data): void
    {
        $reference = $data['reference'] ?? null;
        $status = $data['status'] ?? null;

        if (!$reference || !$status) {
            Log::warning('Tripay callback: Missing reference or status', $data);
            return;
        }

        $transaction = TripayTransaction::where('reference', $reference)->first();

        if (!$transaction) {
            Log::warning('Tripay callback: Transaction not found', ['reference' => $reference]);
            return;
        }

        // Skip if already processed
        if ($transaction->status === TripayTransaction::STATUS_PAID) {
            return;
        }

        // Store raw callback data
        $transaction->update([
            'callback_data' => $data,
        ]);

        if ($status === 'PAID') {
            $this->processSuccessfulPayment($transaction, $data);
        } elseif (in_array($status, ['EXPIRED', 'FAILED'])) {
            $transaction->update(['status' => $status]);
        } elseif ($status === 'REFUND') {
            $transaction->update(['status' => TripayTransaction::STATUS_REFUND]);
        }
    }

    /**
     * Process a successful payment callback
     */
    protected function processSuccessfulPayment(TripayTransaction $transaction, array $data): void
    {
        $customer = $transaction->customer;

        if (!$customer) {
            Log::error('Tripay: Customer not found for transaction', [
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
            ]);
            return;
        }

        // Determine payment method based on Tripay method code
        $paymentMethod = $this->mapPaymentMethod($transaction->method);

        try {
            // Use existing PaymentService to process payment (handles FIFO allocation, debt reduction, reopen)
            $payment = $this->paymentService->processPayment(
                customer: $customer,
                amount: (float) $transaction->amount,
                paymentMethod: $paymentMethod,
                collector: null,
                receivedBy: null,
                transferProof: null,
                notes: "Pembayaran online via Tripay ({$transaction->method}) - Ref: {$transaction->reference}",
                paymentChannel: Payment::CHANNEL_ONLINE,
                referenceNumber: $transaction->reference,
            );

            // Update tripay transaction
            $transaction->update([
                'status' => TripayTransaction::STATUS_PAID,
                'payment_id' => $payment->id,
                'paid_at' => now(),
            ]);

            // Send WA confirmation
            try {
                $this->notificationService->sendPaymentConfirmation($customer, $payment);
            } catch (\Exception $e) {
                Log::warning('Tripay: Failed to send WA confirmation', [
                    'error' => $e->getMessage(),
                    'customer_id' => $customer->id,
                ]);
            }

            Log::info('Tripay: Payment processed successfully', [
                'reference' => $transaction->reference,
                'customer_id' => $customer->id,
                'amount' => $transaction->amount,
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Tripay: Failed to process payment', [
                'error' => $e->getMessage(),
                'reference' => $transaction->reference,
                'customer_id' => $customer->id,
            ]);

            // Still mark the tripay transaction as PAID so we know money was received
            $transaction->update([
                'status' => TripayTransaction::STATUS_PAID,
                'paid_at' => now(),
            ]);
        }
    }

    /**
     * Verify callback signature from Tripay
     */
    public function verifySignature(string $callbackJson): bool
    {
        $config = $this->getConfig();
        $signature = hash_hmac('sha256', $callbackJson, $config['private_key']);

        return $signature === request()->header('X-Callback-Signature');
    }

    /**
     * Calculate signature for creating transaction
     */
    protected function calculateSignature(string $merchantCode, string $merchantRef, int $amount, string $privateKey): string
    {
        return hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey);
    }

    /**
     * Map Tripay method code to our payment method
     */
    protected function mapPaymentMethod(string $tripayMethod): string
    {
        // QRIS
        if (str_contains(strtoupper($tripayMethod), 'QRIS') || $tripayMethod === 'QRIS' || $tripayMethod === 'QRISC' || $tripayMethod === 'QRIS2') {
            return Payment::METHOD_QRIS;
        }

        // E-Wallets
        $ewallets = ['OVO', 'DANA', 'SHOPEEPAY', 'LINKAJA', 'GOPAY'];
        if (in_array(strtoupper($tripayMethod), $ewallets)) {
            return Payment::METHOD_EWALLET;
        }

        // Default: transfer (VA, bank transfer, etc.)
        return Payment::METHOD_TRANSFER;
    }

    /**
     * Check transaction status from Tripay API
     */
    public function checkTransactionStatus(TripayTransaction $transaction): array
    {
        $config = $this->getConfig();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $config['api_key'],
            ])->get($config['base_url'] . '/transaction/detail', [
                'reference' => $transaction->reference,
            ]);

            if ($response->successful()) {
                $data = $response->json('data');

                // Update local status if changed
                $newStatus = $data['status'] ?? $transaction->status;
                if ($newStatus !== $transaction->status) {
                    if ($newStatus === 'PAID' && $transaction->status !== TripayTransaction::STATUS_PAID) {
                        $this->processSuccessfulPayment($transaction, $data);
                    } else {
                        $transaction->update(['status' => $newStatus]);
                    }
                }

                return [
                    'success' => true,
                    'status' => $newStatus,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to check status',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
