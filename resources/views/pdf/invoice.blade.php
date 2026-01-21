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
            font-size: 10px;
            line-height: 1.3;
            color: #333;
        }
        .container {
            padding: 10px 25px;
        }
        @page {
            margin: 10mm;
            size: A4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 8px;
        }
        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .company-info h1 {
            font-size: 18px;
            color: #2563eb;
            margin-bottom: 2px;
        }
        .company-info p {
            color: #666;
            font-size: 9px;
            line-height: 1.4;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-info h2 {
            font-size: 20px;
            color: #333;
            margin-bottom: 4px;
        }
        .invoice-info p {
            font-size: 9px;
            color: #666;
        }
        .invoice-number {
            font-family: monospace;
            font-size: 11px;
            font-weight: bold;
            color: #2563eb;
        }
        .section-title {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 4px;
            font-weight: bold;
        }
        .customer-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        th {
            background-color: #2563eb;
            color: white;
            padding: 6px 8px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #eee;
            font-size: 10px;
        }
        .text-right {
            text-align: right;
        }
        .info-table td {
            border: none;
            padding: 2px 8px;
            font-size: 10px;
        }
        .total-section {
            width: 250px;
            margin-left: auto;
        }
        .total-table {
            width: 100%;
            border-collapse: collapse;
        }
        .total-table td {
            padding: 4px 0;
            border: none;
            border-bottom: 1px solid #eee;
            font-size: 10px;
        }
        .total-table .grand-total td {
            border-top: 2px solid #333;
            border-bottom: none;
            font-size: 12px;
            font-weight: bold;
            padding-top: 8px;
        }
        .outstanding {
            color: #dc2626;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid { background: #dcfce7; color: #16a34a; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
        .status-partial { background: #dbeafe; color: #2563eb; }
        .footer {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 8px;
        }
        .payment-info {
            background: #f8fafc;
            padding: 8px;
            border-radius: 4px;
            margin-top: 8px;
        }
        .payment-info h3 {
            font-size: 10px;
            margin-bottom: 6px;
            color: #333;
        }
        .bank-account {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 4px;
            padding: 4px 8px;
            background: white;
            border-radius: 4px;
            font-size: 9px;
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
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    <div class="company-info">
                        <h1>{{ $ispInfo->company_name ?? 'Java Indonusa' }}</h1>
                        <p>{{ $ispInfo->address ?? '' }}</p>
                        <p>Telp: {{ $ispInfo->phone ?? '' }} | {{ $ispInfo->email ?? '' }}</p>
                    </div>
                </td>
                <td style="width: 40%;">
                    <div class="invoice-info">
                        <h2>INVOICE</h2>
                        <p class="invoice-number">{{ $invoice->invoice_number }}</p>
                        <p>Tanggal: {{ $invoice->created_at->format('d/m/Y') }}</p>
                        <p>Jatuh Tempo: {{ $invoice->due_date->format('d/m/Y') }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Customer & Invoice Details -->
        <table class="info-table" style="margin-bottom: 10px;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-left: 0;">
                    <p class="section-title">Tagihan Kepada</p>
                    <p class="customer-name">{{ $invoice->customer->name }}</p>
                    <p>ID: {{ $invoice->customer->customer_id }} | Telp: {{ $invoice->customer->phone }}</p>
                    <p>{{ $invoice->customer->address }}</p>
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right; padding-right: 0;">
                    <p class="section-title">Detail Invoice</p>
                    <p>Periode: {{ $invoice->period_month }}/{{ $invoice->period_year }} | Paket: {{ $invoice->package_name }}</p>
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
                    <th class="text-right" style="width: 120px;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $invoice->package_name }}</strong>
                        <span style="color: #666;"> - Layanan Internet Periode {{ $invoice->period_month }}/{{ $invoice->period_year }}</span>
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
            <table class="total-table">
                <tr>
                    <td>Subtotal</td>
                    <td class="text-right">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
                </tr>
                @if($invoice->paid_amount > 0)
                <tr>
                    <td>Sudah Dibayar</td>
                    <td class="text-right" style="color: #16a34a;">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</td>
                </tr>
                @endif
                <tr class="grand-total {{ $invoice->remaining_amount > 0 ? 'outstanding' : '' }}">
                    <td>{{ $invoice->remaining_amount > 0 ? 'Sisa Tagihan' : 'Total' }}</td>
                    <td class="text-right">Rp {{ number_format($invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->total_amount, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <!-- Payment Info -->
        @if($invoice->status !== 'paid' && isset($ispInfo->bank_accounts) && count($ispInfo->bank_accounts) > 0)
        <div class="payment-info">
            <h3>Informasi Pembayaran:</h3>
            @foreach($ispInfo->bank_accounts as $bank)
            <div class="bank-account">
                <span class="bank-name">{{ $bank['bank'] }}</span> -
                <span style="font-family: monospace;">{{ $bank['account'] }}</span>
                (a.n {{ $bank['name'] }})
            </div>
            @endforeach
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>Terima kasih atas kepercayaan Anda menggunakan layanan kami.</p>
            <p>{{ $ispInfo->company_name ?? 'Java Indonusa' }} {{ $ispInfo->tagline ? '- ' . $ispInfo->tagline : '' }}</p>
            <p style="margin-top: 4px; font-size: 7px;">Dokumen ini digenerate secara otomatis dan sah tanpa tanda tangan.</p>
        </div>
    </div>
</body>
</html>
