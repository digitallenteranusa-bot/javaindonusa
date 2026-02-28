<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Odp;
use App\Services\Admin\DashboardService;

class CustomerObserver
{
    public function created(Customer $customer): void
    {
        if ($customer->odp_id) {
            $customer->odp?->recalculateUsedPorts();
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

        DashboardService::clearDashboardCache();
    }

    public function deleted(Customer $customer): void
    {
        if ($customer->odp_id) {
            Odp::find($customer->odp_id)?->recalculateUsedPorts();
        }

        DashboardService::clearDashboardCache();
    }
}
