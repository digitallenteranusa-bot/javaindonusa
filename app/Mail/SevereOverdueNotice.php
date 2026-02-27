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

class SevereOverdueNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public int $overdueMonths,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pemberitahuan Tunggakan {$this->overdueMonths} Bulan",
        );
    }

    public function content(): Content
    {
        $ispInfo = IspInfo::getCached();

        $overdueInvoices = Invoice::where('customer_id', $this->customer->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        return new Content(
            view: 'emails.severe-overdue',
            with: [
                'customer' => $this->customer,
                'overdueMonths' => $this->overdueMonths,
                'overdueInvoices' => $overdueInvoices,
                'companyName' => $ispInfo?->company_name ?? config('app.name'),
                'companyPhone' => $ispInfo?->phone_primary ?? '',
                'bankAccounts' => $ispInfo?->bank_accounts ?? [],
            ],
        );
    }
}
