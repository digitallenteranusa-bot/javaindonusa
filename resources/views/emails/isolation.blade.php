@extends('emails.layouts.base')
@section('title', 'Pemberitahuan Isolir')
@section('header-subtitle', 'Pemberitahuan Layanan')

@section('content')
<p>Yth. Bapak/Ibu <strong>{{ $customer->name }}</strong>,</p>

<div class="danger-box">
    <p style="margin:0;">Dengan berat hati kami informasikan bahwa layanan internet Anda telah <strong>DIISOLIR</strong> karena tunggakan pembayaran.</p>
</div>

<table class="detail">
    <tr><td>Total Tunggakan</td><td>Rp {{ number_format($customer->total_debt, 0, ',', '.') }}</td></tr>
    <tr><td>ID Pelanggan</td><td>{{ $customer->customer_id }}</td></tr>
</table>

<p><strong>Untuk mengaktifkan kembali layanan:</strong></p>
<ol>
    <li>Lakukan pembayaran tunggakan</li>
    <li>Kirim bukti transfer via WhatsApp</li>
    <li>Layanan akan aktif dalam 1x24 jam</li>
</ol>

@if(!empty($bankAccounts))
<p><strong>Pembayaran:</strong></p>
@foreach($bankAccounts as $bank)
<p style="margin: 4px 0;">{{ $bank['bank'] }}: {{ $bank['account'] }} a.n {{ $bank['name'] }}</p>
@endforeach
@endif
@endsection
