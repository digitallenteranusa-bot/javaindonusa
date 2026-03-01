<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Odp;
use App\Services\Admin\DashboardService;
use App\Services\Radius\RadiusService;
use Illuminate\Support\Facades\Log;

class CustomerObserver
{
    public function created(Customer $customer): void
    {
        if ($customer->odp_id) {
            $customer->odp?->recalculateUsedPorts();
        }

        // Sync to RADIUS if customer has PPPoE credentials
        if ($customer->pppoe_username) {
            try {
                app(RadiusService::class)->syncCustomer($customer);
            } catch (\Exception $e) {
                Log::warning('RADIUS sync failed on customer create', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DashboardService::clearDashboardCache();
    }

    public function updated(Customer $customer): void
    {
        if ($customer->isDirty('odp_id')) {
            $oldOdpId = $customer->getOriginal('odp_id');

            if ($oldOdpId) {
                Odp::find($oldOdpId)?->recalculateUsedPorts();
            }

            if ($customer->odp_id) {
                $customer->odp?->recalculateUsedPorts();
            }
        }

        // Sync to RADIUS if relevant fields changed
        if ($customer->isDirty(['pppoe_username', 'pppoe_password', 'package_id'])) {
            try {
                $radiusService = app(RadiusService::class);

                // If username changed, remove old entries first
                if ($customer->isDirty('pppoe_username')) {
                    $oldUsername = $customer->getOriginal('pppoe_username');
                    if ($oldUsername) {
                        $radiusService->removeByUsername($oldUsername);
                    }
                }

                // Sync current credentials
                if ($customer->pppoe_username) {
                    $radiusService->syncCustomer($customer);
                }
            } catch (\Exception $e) {
                Log::warning('RADIUS sync failed on customer update', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DashboardService::clearDashboardCache();
    }

    public function deleted(Customer $customer): void
    {
        if ($customer->odp_id) {
            Odp::find($customer->odp_id)?->recalculateUsedPorts();
        }

        // Remove from RADIUS
        if ($customer->pppoe_username) {
            try {
                app(RadiusService::class)->removeCustomer($customer);
            } catch (\Exception $e) {
                Log::warning('RADIUS removal failed on customer delete', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DashboardService::clearDashboardCache();
    }
}
