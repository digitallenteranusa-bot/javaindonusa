@extends('emails.layouts.base')
@section('title', 'Tagihan Baru')
@section('header-subtitle', 'Tagihan Internet')

@section('content')
<p>Yth. Bapak/Ibu <strong>{{ $customer->name }}</strong>,</p>
<p>Tagihan internet Anda untuk periode <strong>{{ $invoice->period_label }}</strong> telah terbit.</p>

<div class="info-box">
    <table class="detail">
        <tr><td>No. Invoice</td><td>{{ $invoice->invoice_number }}</td></tr>
        <tr><td>Paket</td><td>{{ $invoice->package_name }}</td></tr>
        <tr><td>Periode</td><td>{{ $invoice->period_label }}</td></tr>
        <tr><td>Total Tagihan</td><td>Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td></tr>
        <tr><td>Jatuh Tempo</td><td>{{ $invoice->due_date->format('d M Y') }}</td></tr>
    </table>
</div>

<p>Gunakan ID Pelanggan <strong>{{ $customer->customer_id }}</strong> sebagai keterangan transfer.</p>

@if(!empty($bankAccounts))
<p><strong>Pembayaran dapat dilakukan ke:</strong></p>
@foreach($bankAccounts as $bank)
<p style="margin: 4px 0;">{{ $bank['bank'] }}: {{ $bank['account'] }} a.n {{ $bank['name'] }}</p>
@endforeach
@endif

<p>Terima kasih atas kepercayaan Anda.</p>
@endsection
