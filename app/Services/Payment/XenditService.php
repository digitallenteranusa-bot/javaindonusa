<?php

namespace App\Services\Payment;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\XenditTransaction;
use App\Services\Billing\PaymentService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditService
{
    protected PaymentService $paymentService;
    protected NotificationService $notificationService;

    public function __construct(PaymentService $paymentService, NotificationService $notificationService)
    {
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
    }

    /**
     * Check if Xendit is enabled (from DB settings or config)
     */
    public function isEnabled(): bool
    {
        $dbEnabled = Setting::getValue('xendit', 'enabled');
        if ($dbEnabled !== null) {
            return (bool) $dbEnabled;
        }

        return (bool) config('xendit.enabled');
    }

    /**
     * Get Xendit configuration (DB settings take priority over config file)
     */
    protected function getConfig(): array
    {
        $dbSettings = Setting::getGroup('xendit');

        $secretKey = $dbSettings['secret_key'] ?? config('xendit.secret_key');

        return [
            'secret_key' => $secretKey,
            'webhook_token' => $dbSettings['webhook_token'] ?? config('xendit.webhook_token'),
            'base_url' => config('xendit.base_url', 'https://api.xendit.co'),
            'is_sandbox' => str_starts_with($secretKey, 'xnd_development_'),
        ];
    }

    /**
     * Get available payment channels (hardcoded - Xendit has no public channel API)
     */
    public function getPaymentChannels(): array
    {
        return [
            // QRIS
            [
                'code' => 'QRIS',
                'name' => 'QRIS',
                'group' => 'QRIS',
                'icon_url' => null,
            ],
            // Virtual Account
            [
                'code' => 'BCA',
                'name' => 'BCA Virtual Account',
                'group' => 'Virtual Account',
                'icon_url' => null,
            ],
            [
                'code' => 'BNI',
                'name' => 'BNI Virtual Account',
                'group' => 'Virtual Account',
                'icon_url' => null,
            ],
            [
                'code' => 'BRI',
                'name' => 'BRI Virtual Account',
                'group' => 'Virtual Account',
                'icon_url' => null,
            ],
            [
                'code' => 'MANDIRI',
                'name' => 'Mandiri Virtual Account',
                'group' => 'Virtual Account',
                'icon_url' => null,
            ],
            [
                'code' => 'PERMATA',
                'name' => 'Permata Virtual Account',
                'group' => 'Virtual Account',
                'icon_url' => null,
            ],
            // E-Wallet
            [
                'code' => 'DANA',
                'name' => 'DANA',
                'group' => 'E-Wallet',
                'icon_url' => null,
            ],
            [
                'code' => 'OVO',
                'name' => 'OVO',
                'group' => 'E-Wallet',
                'icon_url' => null,
            ],
            [
                'code' => 'SHOPEEPAY',
                'name' => 'ShopeePay',
                'group' => 'E-Wallet',
                'icon_url' => null,
            ],
            [
                'code' => 'LINKAJA',
                'name' => 'LinkAja',
                'group' => 'E-Wallet',
                'icon_url' => null,
            ],
            // Retail Outlet
            [
                'code' => 'ALFAMART',
                'name' => 'Alfamart',
                'group' => 'Convenience Store',
                'icon_url' => null,
            ],
            [
                'code' => 'INDOMARET',
                'name' => 'Indomaret',
                'group' => 'Convenience Store',
                'icon_url' => null,
            ],
        ];
    }

    /**
     * Create a Xendit invoice
     */
    public function createTransaction(Customer $customer, array $invoiceIds, string $method): XenditTransaction
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
        $externalId = 'INV-' . now()->format('Ymd') . '-' . str_pad($customer->id, 5, '0', STR_PAD_LEFT) . '-' . time();

        // Build item descriptions
        $description = $invoices->map(fn(Invoice $inv) => "Tagihan {$inv->period_month}/{$inv->period_year}")
            ->implode(', ');

        $items = $invoices->map(fn(Invoice $inv) => [
            'name' => "Tagihan {$inv->period_month}/{$inv->period_year}",
            'quantity' => 1,
            'price' => (int) $inv->remaining_amount,
        ])->toArray();

        // Build payment methods filter for Xendit
        $paymentMethods = $this->mapToXenditPaymentMethods($method);

        $payload = [
            'external_id' => $externalId,
            'amount' => $amount,
            'description' => $description,
            'customer' => [
                'given_names' => $customer->name,
                'email' => $customer->email ?: 'noemail@javaindonusa.net',
                'mobile_number' => $this->formatPhone($customer->phone),
            ],
            'items' => $items,
            'success_redirect_url' => url('/portal'),
            'failure_redirect_url' => url('/portal/pay'),
            'invoice_duration' => 86400, // 24 hours in seconds
        ];

        // Add payment methods filter if specified
        if (!empty($paymentMethods)) {
            $payload['payment_methods'] = $paymentMethods;
        }

        try {
            $response = Http::withBasicAuth($config['secret_key'], '')
                ->post($config['base_url'] . '/v2/invoices', $payload);

            if (!$response->successful()) {
                Log::error('Xendit: Failed to create invoice', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload' => array_merge($payload, ['customer' => '***']),
                ]);
                throw new \Exception('Gagal membuat transaksi: ' . ($response->json('message') ?? 'Unknown error'));
            }

            $data = $response->json();

            $primaryInvoiceId = $invoices->count() === 1 ? $invoices->first()->id : null;

            $transaction = XenditTransaction::create([
                'customer_id' => $customer->id,
                'invoice_id' => $primaryInvoiceId,
                'xendit_id' => $data['id'],
                'external_id' => $externalId,
                'method' => $method,
                'amount' => $amount,
                'status' => XenditTransaction::STATUS_PENDING,
                'invoice_url' => $data['invoice_url'] ?? null,
                'expired_at' => isset($data['expiry_date'])
                    ? \Carbon\Carbon::parse($data['expiry_date'])
                    : now()->addHours(24),
            ]);

            return $transaction;
        } catch (\Exception $e) {
            if (!str_starts_with($e->getMessage(), 'Gagal membuat transaksi:')) {
                Log::error('Xendit: Exception creating invoice', [
                    'error' => $e->getMessage(),
                ]);
            }
            throw $e;
        }
    }

    /**
     * Handle callback from Xendit webhook
     */
    public function handleCallback(array $data): void
    {
        $xenditId = $data['id'] ?? null;
        $externalId = $data['external_id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$externalId || !$status) {
            Log::warning('Xendit callback: Missing external_id or status', $data);
            return;
        }

        $transaction = XenditTransaction::where('external_id', $externalId)->first();

        if (!$transaction) {
            Log::warning('Xendit callback: Transaction not found', ['external_id' => $externalId]);
            return;
        }

        // Skip if already processed
        if ($transaction->status === XenditTransaction::STATUS_PAID) {
            return;
        }

        // Store raw callback data
        $transaction->update([
            'callback_data' => $data,
        ]);

        if ($status === 'PAID' || $status === 'SETTLED') {
            // Update method from callback if available
            $paymentMethod = $data['payment_method'] ?? $data['payment_channel'] ?? $transaction->method;
            $fee = $data['fees_paid_amount'] ?? 0;
            $transaction->update(['method' => $paymentMethod, 'fee' => $fee]);

            $this->processSuccessfulPayment($transaction, $data);
        } elseif ($status === 'EXPIRED') {
            $transaction->update(['status' => XenditTransaction::STATUS_EXPIRED]);
        } elseif (in_array($status, ['FAILED', 'VOIDED'])) {
            $transaction->update(['status' => XenditTransaction::STATUS_FAILED]);
        }
    }

    /**
     * Process a successful payment callback
     */
    protected function processSuccessfulPayment(XenditTransaction $transaction, array $data): void
    {
        $customer = $transaction->customer;

        if (!$customer) {
            Log::error('Xendit: Customer not found for transaction', [
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
            ]);
            return;
        }

        $paymentMethod = $this->mapPaymentMethod($transaction->method);

        try {
            $payment = $this->paymentService->processPayment(
                customer: $customer,
                amount: (float) $transaction->amount,
                paymentMethod: $paymentMethod,
                collector: null,
                receivedBy: null,
                transferProof: null,
                notes: "Pembayaran online via Xendit ({$transaction->method}) - ID: {$transaction->external_id}",
                paymentChannel: Payment::CHANNEL_ONLINE,
                referenceNumber: $transaction->external_id,
            );

            $transaction->update([
                'status' => XenditTransaction::STATUS_PAID,
                'payment_id' => $payment->id,
                'paid_at' => now(),
            ]);

            // Send WA confirmation
            try {
                $this->notificationService->sendPaymentConfirmation($customer, $payment);
            } catch (\Exception $e) {
                Log::warning('Xendit: Failed to send WA confirmation', [
                    'error' => $e->getMessage(),
                    'customer_id' => $customer->id,
                ]);
            }

            Log::info('Xendit: Payment processed successfully', [
                'external_id' => $transaction->external_id,
                'customer_id' => $customer->id,
                'amount' => $transaction->amount,
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Xendit: Failed to process payment', [
                'error' => $e->getMessage(),
                'external_id' => $transaction->external_id,
                'customer_id' => $customer->id,
            ]);

            // Still mark as PAID so we know money was received
            $transaction->update([
                'status' => XenditTransaction::STATUS_PAID,
                'paid_at' => now(),
            ]);
        }
    }

    /**
     * Verify callback token from Xendit webhook
     */
    public function verifyWebhookToken(string $callbackToken): bool
    {
        $config = $this->getConfig();
        return $callbackToken === $config['webhook_token'];
    }

    /**
     * Map selected payment method to Xendit payment_methods array
     */
    protected function mapToXenditPaymentMethods(string $method): array
    {
        $map = [
            'QRIS' => ['QRIS'],
            'BCA' => ['BCA'],
            'BNI' => ['BNI'],
            'BRI' => ['BRI'],
            'MANDIRI' => ['MANDIRI'],
            'PERMATA' => ['PERMATA'],
            'DANA' => ['DANA'],
            'OVO' => ['OVO'],
            'SHOPEEPAY' => ['SHOPEEPAY'],
            'LINKAJA' => ['LINKAJA'],
            'ALFAMART' => ['ALFAMART'],
            'INDOMARET' => ['INDOMARET'],
        ];

        return $map[strtoupper($method)] ?? [];
    }

    /**
     * Map Xendit method to our internal payment method
     */
    protected function mapPaymentMethod(string $xenditMethod): string
    {
        $method = strtoupper($xenditMethod);

        if (str_contains($method, 'QRIS') || $method === 'QRIS') {
            return Payment::METHOD_QRIS;
        }

        $ewallets = ['OVO', 'DANA', 'SHOPEEPAY', 'LINKAJA', 'GOPAY'];
        if (in_array($method, $ewallets)) {
            return Payment::METHOD_EWALLET;
        }

        return Payment::METHOD_TRANSFER;
    }

    /**
     * Check transaction status from Xendit API
     */
    public function checkTransactionStatus(XenditTransaction $transaction): array
    {
        $config = $this->getConfig();

        if (!$transaction->xendit_id) {
            return [
                'success' => false,
                'message' => 'No Xendit ID',
            ];
        }

        try {
            $response = Http::withBasicAuth($config['secret_key'], '')
                ->get($config['base_url'] . '/v2/invoices/' . $transaction->xendit_id);

            if ($response->successful()) {
                $data = $response->json();

                $newStatus = $data['status'] ?? $transaction->status;

                if ($newStatus !== $transaction->status) {
                    if (in_array($newStatus, ['PAID', 'SETTLED']) && $transaction->status !== XenditTransaction::STATUS_PAID) {
                        $this->processSuccessfulPayment($transaction, $data);
                    } elseif ($newStatus === 'EXPIRED') {
                        $transaction->update(['status' => XenditTransaction::STATUS_EXPIRED]);
                    } elseif (in_array($newStatus, ['FAILED', 'VOIDED'])) {
                        $transaction->update(['status' => XenditTransaction::STATUS_FAILED]);
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

    /**
     * Format phone number for Xendit
     */
    protected function formatPhone(?string $phone): string
    {
        if (!$phone) return '';

        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '+62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '62')) {
            $phone = '+' . $phone;
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+62' . $phone;
        }

        return $phone;
    }
}
