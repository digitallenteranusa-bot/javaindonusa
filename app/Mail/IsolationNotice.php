<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\IspInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IsolationNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pemberitahuan Isolir Layanan Internet',
        );
    }

    public function content(): Content
    {
        $ispInfo = IspInfo::getCached();

        return new Content(
            view: 'emails.isolation',
            with: [
                'customer' => $this->customer,
                'companyName' => $ispInfo?->company_name ?? config('app.name'),
                'companyPhone' => $ispInfo?->phone_primary ?? '',
                'bankAccounts' => $ispInfo?->bank_accounts ?? [],
            ],
        );
    }
}
