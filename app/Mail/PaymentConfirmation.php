<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\IspInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public Payment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Konfirmasi Pembayaran Internet',
        );
    }

    public function content(): Content
    {
        $ispInfo = IspInfo::getCached();

        return new Content(
            view: 'emails.payment-confirmation',
            with: [
                'customer' => $this->customer,
                'payment' => $this->payment,
                'companyName' => $ispInfo?->company_name ?? config('app.name'),
                'companyPhone' => $ispInfo?->phone_primary ?? '',
            ],
        );
    }
}
