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

class UnsuspendCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public int $customerId;

    public function __construct(int $customerId)
    {
        $this->customerId = $customerId;

        $this->onQueue('isolation');
    }

    public function handle(
        MikrotikService $mikrotikService,
        NotificationService $notificationService
    ): void {
        $customer = Customer::with(['router', 'package'])->find($this->customerId);

        if (!$customer) {
            Log::warning('UnsuspendCustomerJob: Customer not found', [
                'customer_id' => $this->customerId,
            ]);
            return;
        }

        if ($customer->status !== Customer::STATUS_SUSPENDED) {
            Log::info('UnsuspendCustomerJob: Customer not suspended, skipping', [
                'customer_id' => $customer->id,
                'status' => $customer->status,
            ]);
            return;
        }

        try {
            // Re-enable internet via Mikrotik (reuse reopen logic)
            $result = $mikrotikService->reopenCustomer($customer);

            if ($result['success']) {
                $customer->update([
                    'status' => Customer::STATUS_ACTIVE,
                    'suspension_start_date' => null,
                    'suspension_end_date' => null,
                    'suspension_reason' => null,
                ]);

                // Sync reopen to RADIUS (restore Framed-Pool, rate-limit, group)
                try {
                    app(RadiusService::class)->reopenCustomer($customer);
                } catch (\Exception $e) {
                    Log::warning('RADIUS reopen failed (unsuspend)', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                Log::info('Customer unsuspended successfully', [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                ]);

                // Send notification
                $notificationService->sendWhatsApp(
                    $customer->phone,
                    $this->buildUnsuspensionMessage($customer)
                );
            } else {
                Log::warning('Customer unsuspension failed on router', [
                    'customer_id' => $customer->id,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);

                if ($this->attempts() < $this->tries) {
                    throw new \Exception($result['message'] ?? 'Unsuspension failed');
                }
            }
        } catch (\Exception $e) {
            Log::error('UnsuspendCustomerJob error', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function buildUnsuspensionMessage(Customer $customer): string
    {
        $message = "Yth. {$customer->name},\n\n";
        $message .= "Layanan internet Anda telah diaktifkan kembali pada tanggal " . now()->format('d/m/Y') . ".\n";
        $message .= "Masa cuti telah berakhir dan tagihan akan berjalan kembali mulai bulan depan.\n";
        $message .= "\nTerima kasih telah menggunakan layanan kami.";

        return $message;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('UnsuspendCustomerJob failed permanently', [
            'customer_id' => $this->customerId,
            'error' => $exception->getMessage(),
        ]);
    }
}
