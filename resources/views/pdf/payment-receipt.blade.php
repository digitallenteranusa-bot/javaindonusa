<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kwitansi {{ $payment->payment_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .container {
            padding: 30px;
            max-width: 400px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px dashed #ccc;
        }
        .header h1 {
            font-size: 18px;
            color: #16a34a;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 10px;
            color: #666;
        }
        .receipt-title {
            text-align: center;
            margin: 20px 0;
        }
        .receipt-title h2 {
            font-size: 20px;
            color: #333;
        }
        .receipt-number {
            font-family: monospace;
            font-size: 14px;
            color: #16a34a;
            margin-top: 5px;
        }
        .info-section {
            margin: 20px 0;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dotted #ddd;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666;
        }
        .info-value {
            font-weight: bold;
            text-align: right;
        }
        .amount-section {
            text-align: center;
            margin: 25px 0;
            padding: 20px;
            background: #dcfce7;
            border-radius: 8px;
        }
        .amount-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .amount-value {
            font-size: 28px;
            font-weight: bold;
            color: #16a34a;
        }
        .amount-words {
            font-size: 10px;
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        .allocation-section {
            margin: 20px 0;
        }
        .allocation-title {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .allocation-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 10px;
            background: #f1f5f9;
            border-radius: 4px;
            margin-bottom: 5px;
            font-size: 11px;
        }
        .method-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .method-cash {
            background: #dcfce7;
            color: #16a34a;
        }
        .method-transfer {
            background: #dbeafe;
            color: #2563eb;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px dashed #ccc;
            text-align: center;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding: 0 20px;
        }
        .signature-box {
            text-align: center;
            width: 45%;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
            font-size: 11px;
        }
        .footer-note {
            font-size: 9px;
            color: #999;
            text-align: center;
            margin-top: 20px;
        }
        .status-cancelled {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $ispInfo->company_name ?? 'Java Indonusa' }}</h1>
            <p>{{ $ispInfo->address ?? '' }}</p>
            <p>Telp: {{ $ispInfo->phone ?? '' }}</p>
        </div>

        @if($payment->status === 'cancelled')
        <div class="status-cancelled">
            DIBATALKAN
        </div>
        @endif

        <!-- Receipt Title -->
        <div class="receipt-title">
            <h2>BUKTI PEMBAYARAN</h2>
            <p class="receipt-number">{{ $payment->payment_number }}</p>
        </div>

        <!-- Payment Info -->
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Tanggal</span>
                <span class="info-value">{{ $payment->created_at->format('d F Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Waktu</span>
                <span class="info-value">{{ $payment->created_at->format('H:i') }} WIB</span>
            </div>
            <div class="info-row">
                <span class="info-label">Metode</span>
                <span class="info-value">
                    <span class="method-badge method-{{ $payment->payment_method }}">
                        {{ $payment->payment_method === 'cash' ? 'Tunai' : 'Transfer' }}
                    </span>
                </span>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">ID Pelanggan</span>
                <span class="info-value">{{ $payment->customer->customer_id }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Nama</span>
                <span class="info-value">{{ $payment->customer->name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Alamat</span>
                <span class="info-value" style="font-size: 10px; max-width: 200px;">{{ $payment->customer->address }}</span>
            </div>
        </div>

        <!-- Amount -->
        <div class="amount-section">
            <p class="amount-label">Jumlah Pembayaran</p>
            <p class="amount-value">Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
            <p class="amount-words"># {{ $amountInWords ?? '' }} Rupiah #</p>
        </div>

        <!-- Allocation -->
        @if($payment->invoices && $payment->invoices->count() > 0)
        <div class="allocation-section">
            <p class="allocation-title">Dialokasikan ke Invoice</p>
            @foreach($payment->invoices as $invoice)
            <div class="allocation-item">
                <span>{{ $invoice->invoice_number }} ({{ $invoice->period_month }}/{{ $invoice->period_year }})</span>
                <span>Rp {{ number_format($invoice->pivot->amount, 0, ',', '.') }}</span>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Notes -->
        @if($payment->notes)
        <div class="info-section" style="background: #fefce8;">
            <p style="font-size: 11px;"><strong>Catatan:</strong> {{ $payment->notes }}</p>
        </div>
        @endif

        <!-- Signature -->
        <div class="signature-section">
            <div class="signature-box">
                <p style="font-size: 10px; color: #666;">Pelanggan</p>
                <p class="signature-line">{{ $payment->customer->name }}</p>
            </div>
            <div class="signature-box">
                <p style="font-size: 10px; color: #666;">Petugas</p>
                <p class="signature-line">{{ $payment->collector?->name ?? $payment->receivedBy?->name ?? '-' }}</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-note">
            <p>Simpan bukti pembayaran ini sebagai tanda terima yang sah.</p>
            <p>{{ $ispInfo->company_name ?? 'Java Indonusa' }} - {{ $ispInfo->tagline ?? '' }}</p>
            <p style="margin-top: 5px;">Dicetak pada: {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>
</body>
</html>
