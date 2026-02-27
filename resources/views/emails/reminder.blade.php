@extends('emails.layouts.base')
@section('title', 'Pengingat Pembayaran')
@section('header-subtitle', 'Pengingat Tagihan')

@section('content')
<p>Yth. Bapak/Ibu <strong>{{ $customer->name }}</strong>,</p>

<div class="warning-box">
    <p style="margin:0;"><strong>Tagihan Anda sebesar Rp {{ number_format($customer->total_debt, 0, ',', '.') }}</strong> akan jatuh tempo dalam <strong>{{ $daysBeforeDue }} hari</strong>.</p>
</div>

<p>Mohon segera lakukan pembayaran untuk menghindari pemutusan layanan.</p>

<p>ID Pelanggan: <strong>{{ $customer->customer_id }}</strong></p>

@if(!empty($bankAccounts))
<p><strong>Pembayaran:</strong></p>
@foreach($bankAccounts as $bank)
<p style="margin: 4px 0;">{{ $bank['bank'] }}: {{ $bank['account'] }} a.n {{ $bank['name'] }}</p>
@endforeach
@endif

<p><em>Abaikan pesan ini jika sudah melakukan pembayaran.</em></p>
@endsection
