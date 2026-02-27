@extends('emails.layouts.base')
@section('title', 'Tagihan Jatuh Tempo')
@section('header-subtitle', 'Pemberitahuan Tagihan')

@section('content')
<p>Yth. Bapak/Ibu <strong>{{ $customer->name }}</strong>,</p>

<div class="danger-box">
    <p style="margin:0;"><strong>Tagihan Anda sebesar Rp {{ number_format($customer->total_debt, 0, ',', '.') }}</strong> telah melewati jatuh tempo.</p>
</div>

<p>Mohon <strong>segera</strong> lakukan pembayaran untuk menghindari isolir/pemutusan layanan.</p>

<p>ID Pelanggan: <strong>{{ $customer->customer_id }}</strong></p>

@if(!empty($bankAccounts))
<p><strong>Pembayaran:</strong></p>
@foreach($bankAccounts as $bank)
<p style="margin: 4px 0;">{{ $bank['bank'] }}: {{ $bank['account'] }} a.n {{ $bank['name'] }}</p>
@endforeach
@endif

<p>Hubungi kami jika ada kendala pembayaran.</p>
@endsection
