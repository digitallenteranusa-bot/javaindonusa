<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\IspInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tagihan Internet Periode {$this->invoice->period_label}",
        );
    }

    public function content(): Content
    {
        $ispInfo = IspInfo::getCached();

        return new Content(
            view: 'emails.invoice',
            with: [
                'customer' => $this->customer,
                'invoice' => $this->invoice,
                'companyName' => $ispInfo?->company_name ?? config('app.name'),
                'companyPhone' => $ispInfo?->phone_primary ?? '',
                'bankAccounts' => $ispInfo?->bank_accounts ?? [],
            ],
        );
    }
}
