@extends('emails.layouts.base')
@section('title', 'Pemberitahuan Maintenance')
@section('header-subtitle', 'Informasi Layanan')

@section('content')
<p>Yth. Bapak/Ibu <strong>{{ $customer->name }}</strong>,</p>

<div class="warning-box">
    <p style="margin:0;">Kami informasikan bahwa akan dilakukan maintenance/perbaikan pada jaringan kami.</p>
</div>

<table class="detail">
    <tr><td>Waktu Mulai</td><td>{{ $startTime }}</td></tr>
    <tr><td>Estimasi Selesai</td><td>{{ $endTime }}</td></tr>
</table>

<p><strong>Keterangan:</strong><br>{{ $description }}</p>

<p>Selama proses ini, layanan internet Anda mungkin mengalami gangguan sementara. Kami mohon maaf atas ketidaknyamanannya.</p>
@endsection
