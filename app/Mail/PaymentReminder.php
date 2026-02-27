<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\IspInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public int $daysBeforeDue = 3,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengingat Pembayaran Tagihan Internet',
        );
    }

    public function content(): Content
    {
        $ispInfo = IspInfo::getCached();

        return new Content(
            view: 'emails.reminder',
            with: [
                'customer' => $this->customer,
                'daysBeforeDue' => $this->daysBeforeDue,
                'companyName' => $ispInfo?->company_name ?? config('app.name'),
                'companyPhone' => $ispInfo?->phone_primary ?? '',
                'bankAccounts' => $ispInfo?->bank_accounts ?? [],
            ],
        );
    }
}
