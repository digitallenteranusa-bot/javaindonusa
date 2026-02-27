@extends('emails.layouts.base')
@section('title', 'Pemberitahuan Tunggakan')
@section('header-subtitle', 'Pemberitahuan Penting')

@section('content')
<p>Yth. Bapak/Ibu <strong>{{ $customer->name }}</strong>,</p>

<p>Dengan hormat, kami ingin menyampaikan bahwa tagihan internet Anda telah menunggak selama <strong>{{ $overdueMonths }} bulan</strong>.</p>

<div class="danger-box">
    <p style="margin:0 0 8px;"><strong>Rincian Tunggakan:</strong></p>
    @foreach($overdueInvoices as $inv)
    <p style="margin: 2px 0;">{{ $inv->period_label }}: Rp {{ number_format($inv->remaining_amount, 0, ',', '.') }}</p>
    @endforeach
    <hr style="border:none;border-top:1px solid #fca5a5;margin:8px 0;">
    <p style="margin:0;"><strong>Total: Rp {{ number_format($customer->total_debt, 0, ',', '.') }}</strong></p>
</div>

<p>Kami memahami bahwa setiap pelanggan memiliki kondisi yang berbeda. Jika Bapak/Ibu mengalami kendala dalam pembayaran, kami dengan senang hati dapat membantu mencari solusi terbaik.</p>

<p>ID Pelanggan: <strong>{{ $customer->customer_id }}</strong></p>

@if(!empty($bankAccounts))
<p><strong>Pembayaran dapat dilakukan ke:</strong></p>
@foreach($bankAccounts as $bank)
<p style="margin: 4px 0;">{{ $bank['bank'] }}: {{ $bank['account'] }} a.n {{ $bank['name'] }}</p>
@endforeach
@endif

<p>Hormat kami,<br><strong>{{ $companyName }}</strong></p>
@endsection
