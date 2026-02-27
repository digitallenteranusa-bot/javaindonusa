@extends('emails.layouts.base')
@section('title', 'Konfirmasi Pembayaran')
@section('header-subtitle', 'Konfirmasi Pembayaran')

@section('content')
<p>Yth. Bapak/Ibu <strong>{{ $customer->name }}</strong>,</p>

<div class="success-box">
    <p style="margin:0;">Pembayaran Anda telah kami terima.</p>
</div>

<table class="detail">
    <tr><td>No. Pembayaran</td><td>{{ $payment->payment_number }}</td></tr>
    <tr><td>Jumlah</td><td>Rp {{ number_format($payment->amount, 0, ',', '.') }}</td></tr>
    <tr><td>Metode</td><td>{{ $payment->method_label }}</td></tr>
    <tr><td>Tanggal</td><td>{{ $payment->created_at->format('d M Y H:i') }}</td></tr>
    <tr>
        <td>Status</td>
        <td>
            @if($customer->total_debt > 0)
                Sisa tagihan: Rp {{ number_format($customer->total_debt, 0, ',', '.') }}
            @else
                LUNAS
            @endif
        </td>
    </tr>
</table>

<p>Terima kasih.</p>
@endsection
