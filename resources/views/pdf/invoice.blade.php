<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
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
            padding: 20px 30px;
            max-height: 100%;
        }
        @page {
            margin: 10mm;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
        }
        .company-info h1 {
            font-size: 24px;
            color: #2563eb;
            margin-bottom: 5px;
        }
        .company-info p {
            color: #666;
            font-size: 11px;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-info h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        .invoice-info p {
            font-size: 11px;
            color: #666;
        }
        .invoice-number {
            font-family: monospace;
            font-size: 14px;
            font-weight: bold;
            color: #2563eb;
        }
        .customer-section {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
        }
        .customer-info, .invoice-details {
            width: 48%;
        }
        .section-title {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .customer-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #2563eb;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .text-right {
            text-align: right;
        }
        .total-section {
            width: 300px;
            margin-left: auto;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .total-row.grand-total {
            border-top: 2px solid #333;
            border-bottom: none;
            font-size: 16px;
            font-weight: bold;
            padding-top: 15px;
        }
        .total-row.outstanding {
            color: #dc2626;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid { background: #dcfce7; color: #16a34a; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
        .status-partial { background: #dbeafe; color: #2563eb; }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        .payment-info {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .payment-info h3 {
            font-size: 14px;
            margin-bottom: 15px;
            color: #333;
        }
        .bank-account {
            margin-bottom: 10px;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .bank-name {
            font-weight: bold;
            color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <h1>{{ $ispInfo->company_name ?? 'Java Indonusa' }}</h1>
                <p>{{ $ispInfo->address ?? '' }}</p>
                <p>Telp: {{ $ispInfo->phone ?? '' }}</p>
                <p>{{ $ispInfo->email ?? '' }}</p>
            </div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <p class="invoice-number">{{ $invoice->invoice_number }}</p>
                <p>Tanggal: {{ $invoice->created_at->format('d F Y') }}</p>
                <p>Jatuh Tempo: {{ $invoice->due_date->format('d F Y') }}</p>
            </div>
        </div>

        <!-- Customer & Invoice Details -->
        <table style="margin-bottom: 30px;">
            <tr>
                <td style="width: 50%; vertical-align: top; border: none; padding-left: 0;">
                    <p class="section-title">Tagihan Kepada</p>
                    <p class="customer-name">{{ $invoice->customer->name }}</p>
                    <p>ID: {{ $invoice->customer->customer_id }}</p>
                    <p>{{ $invoice->customer->address }}</p>
                    <p>Telp: {{ $invoice->customer->phone }}</p>
                </td>
                <td style="width: 50%; vertical-align: top; border: none; text-align: right; padding-right: 0;">
                    <p class="section-title">Detail Invoice</p>
                    <p>Periode: {{ $invoice->period_month }}/{{ $invoice->period_year }}</p>
                    <p>Paket: {{ $invoice->package_name }}</p>
                    <p>
                        Status:
                        <span class="status-badge status-{{ $invoice->status }}">
                            @switch($invoice->status)
                                @case('paid') Lunas @break
                                @case('pending') Belum Bayar @break
                                @case('partial') Sebagian @break
                                @case('overdue') Jatuh Tempo @break
                                @default {{ $invoice->status }}
                            @endswitch
                        </span>
                    </p>
                </td>
            </tr>
        </table>

        <!-- Invoice Items -->
        <table>
            <thead>
                <tr>
                    <th>Deskripsi</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $invoice->package_name }}</strong><br>
                        <small style="color: #666;">Layanan Internet Periode {{ $invoice->period_month }}/{{ $invoice->period_year }}</small>
                    </td>
                    <td class="text-right">Rp {{ number_format($invoice->package_price, 0, ',', '.') }}</td>
                </tr>
                @if($invoice->additional_charges > 0)
                <tr>
                    <td>Biaya Tambahan</td>
                    <td class="text-right">Rp {{ number_format($invoice->additional_charges, 0, ',', '.') }}</td>
                </tr>
                @endif
                @if($invoice->discount > 0)
                <tr>
                    <td>Diskon</td>
                    <td class="text-right" style="color: #16a34a;">- Rp {{ number_format($invoice->discount, 0, ',', '.') }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        <!-- Totals -->
        <div class="total-section">
            <div class="total-row">
                <span>Subtotal</span>
                <span>Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</span>
            </div>
            @if($invoice->paid_amount > 0)
            <div class="total-row">
                <span>Sudah Dibayar</span>
                <span style="color: #16a34a;">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</span>
            </div>
            @endif
            <div class="total-row grand-total {{ $invoice->remaining_amount > 0 ? 'outstanding' : '' }}">
                <span>{{ $invoice->remaining_amount > 0 ? 'Sisa Tagihan' : 'Total' }}</span>
                <span>Rp {{ number_format($invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->total_amount, 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- Payment Info -->
        @if($invoice->status !== 'paid' && isset($ispInfo->bank_accounts) && count($ispInfo->bank_accounts) > 0)
        <div class="payment-info">
            <h3>Informasi Pembayaran</h3>
            @foreach($ispInfo->bank_accounts as $bank)
            <div class="bank-account">
                <span class="bank-name">{{ $bank['bank'] }}</span><br>
                <span style="font-family: monospace; font-size: 14px;">{{ $bank['account'] }}</span><br>
                <small>a.n {{ $bank['name'] }}</small>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>Terima kasih atas kepercayaan Anda menggunakan layanan kami.</p>
            <p>{{ $ispInfo->company_name ?? 'Java Indonusa' }} - {{ $ispInfo->tagline ?? '' }}</p>
            <p style="margin-top: 10px; font-size: 9px;">Dokumen ini digenerate secara otomatis dan sah tanpa tanda tangan.</p>
        </div>
    </div>
</body>
</html>
