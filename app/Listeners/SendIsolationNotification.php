<?php

namespace App\Listeners;

use App\Events\CustomerIsolated;
use App\Services\Notification\NotificationService;

class SendIsolationNotification
{
    public function __construct(
        protected NotificationService $notification,
    ) {}

    public function handle(CustomerIsolated $event): void
    {
        $this->notification->sendIsolationNotice($event->customer, $event->reason);
    }
}
