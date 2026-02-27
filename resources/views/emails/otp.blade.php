@extends('emails.layouts.base')
@section('title', 'Kode OTP')
@section('header-subtitle', 'Verifikasi Akun')

@section('content')
<p>Kode OTP Anda:</p>

<div style="text-align: center; margin: 24px 0;">
    <span style="display: inline-block; background: #f0f7ff; border: 2px solid #1a56db; padding: 16px 32px; font-size: 32px; font-weight: 700; letter-spacing: 8px; border-radius: 8px;">{{ $otp }}</span>
</div>

<p>Kode berlaku selama <strong>5 menit</strong>.</p>
<p style="color: #ef4444;"><strong>Jangan berikan kode ini kepada siapapun.</strong></p>
@endsection
