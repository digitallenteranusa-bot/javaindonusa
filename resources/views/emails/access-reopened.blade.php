@extends('emails.layouts.base')
@section('title', 'Layanan Aktif Kembali')
@section('header-subtitle', 'Informasi Layanan')

@section('content')
<p>Yth. Bapak/Ibu <strong>{{ $customer->name }}</strong>,</p>

<div class="success-box">
    <p style="margin:0;">Pembayaran Anda telah kami terima. Layanan internet Anda telah <strong>AKTIF KEMBALI</strong>.</p>
</div>

<p>Terima kasih atas kepercayaan Anda menggunakan layanan kami.</p>

<p>Jika ada kendala koneksi, silakan hubungi kami.</p>
@endsection
