<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\IspInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MaintenanceNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public string $startTime,
        public string $endTime,
        public string $description,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pemberitahuan Maintenance Jaringan',
        );
    }

    public function content(): Content
    {
        $ispInfo = IspInfo::getCached();

        return new Content(
            view: 'emails.maintenance',
            with: [
                'customer' => $this->customer,
                'startTime' => $this->startTime,
                'endTime' => $this->endTime,
                'description' => $this->description,
                'companyName' => $ispInfo?->company_name ?? config('app.name'),
                'companyPhone' => $ispInfo?->phone_primary ?? '',
            ],
        );
    }
}
