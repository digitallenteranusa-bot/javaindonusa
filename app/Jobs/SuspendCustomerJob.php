<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Notification\NotificationService;
use App\Services\Radius\RadiusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SuspendCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public int $customerId;
    public string $reason;
    public ?string $endDate;

    public function __construct(int $customerId, string $reason, ?string $endDate = null)
    {
        $this->customerId = $customerId;
        $this->reason = $reason;
        $this->endDate = $endDate;

        $this->onQueue('isolation');
    }

    public function handle(
        MikrotikService $mikrotikService,
        NotificationService $notificationService
    ): void {
        $customer = Customer::with(['router', 'package'])->find($this->customerId);

        if (!$customer) {
            Log::warning('SuspendCustomerJob: Customer not found', [
                'customer_id' => $this->customerId,
            ]);
            return;
        }

        if ($customer->status !== Customer::STATUS_ACTIVE) {
            Log::info('SuspendCustomerJob: Customer not active, skipping', [
                'customer_id' => $customer->id,
                'status' => $customer->status,
            ]);
            return;
        }

        try {
            // Disable internet via Mikrotik (reuse isolation logic)
            $result = $mikrotikService->isolateCustomer($customer);

            if ($result['success']) {
                $customer->update([
                    'status' => Customer::STATUS_SUSPENDED,
                    'suspension_start_date' => now()->toDateString(),
                    'suspension_end_date' => $this->endDate,
                    'suspension_reason' => $this->reason,
                ]);

                // Sync isolation to RADIUS (set isolir pool/group/address-list)
                try {
                    app(RadiusService::class)->isolateCustomer($customer);
                } catch (\Exception $e) {
                    Log::warning('RADIUS isolate failed (suspend)', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                Log::info('Customer suspended successfully', [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'reason' => $this->reason,
                ]);

                // Send notification
                $notificationService->sendWhatsApp(
                    $customer->phone,
                    $this->buildSuspensionMessage($customer)
                );
            } else {
                Log::warning('Customer suspension failed on router', [
                    'customer_id' => $customer->id,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);

                if ($this->attempts() < $this->tries) {
                    throw new \Exception($result['message'] ?? 'Suspension failed');
                }
            }
        } catch (\Exception $e) {
            Log::error('SuspendCustomerJob error', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function buildSuspensionMessage(Customer $customer): string
    {
        $message = "Yth. {$customer->name},\n\n";
        $message .= "Layanan internet Anda telah di-cuti-kan (suspend) mulai tanggal " . now()->format('d/m/Y') . ".\n";
        $message .= "Alasan: {$this->reason}\n";

        if ($this->endDate) {
            $message .= "Perkiraan aktif kembali: " . date('d/m/Y', strtotime($this->endDate)) . "\n";
        }

        $message .= "\nSelama masa cuti, layanan internet tidak aktif dan tagihan tidak berjalan.\n";
        $message .= "Hubungi admin untuk mengaktifkan kembali.\n\nTerima kasih.";

        return $message;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SuspendCustomerJob failed permanently', [
            'customer_id' => $this->customerId,
            'error' => $exception->getMessage(),
        ]);
    }
}
