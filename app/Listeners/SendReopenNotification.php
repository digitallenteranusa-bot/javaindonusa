<?php

namespace App\Listeners;

use App\Events\CustomerReopened;
use App\Services\Notification\NotificationService;

class SendReopenNotification
{
    public function __construct(
        protected NotificationService $notification,
    ) {}

    public function handle(CustomerReopened $event): void
    {
        $this->notification->sendAccessOpenedNotice($event->customer);
    }
}
