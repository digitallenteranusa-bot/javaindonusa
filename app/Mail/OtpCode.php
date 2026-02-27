<?php

namespace App\Mail;

use App\Models\IspInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode OTP Anda',
        );
    }

    public function content(): Content
    {
        $ispInfo = IspInfo::getCached();

        return new Content(
            view: 'emails.otp',
            with: [
                'otp' => $this->otp,
                'companyName' => $ispInfo?->company_name ?? config('app.name'),
                'companyPhone' => $ispInfo?->phone_primary ?? '',
            ],
        );
    }
}
