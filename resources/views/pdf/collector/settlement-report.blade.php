<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Setoran Penagih</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        .container {
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
        }
        .report-title {
            font-size: 14px;
            margin-top: 5px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            background: #f0f0f0;
            padding: 8px 10px;
            margin: 15px 0 10px 0;
            border-left: 4px solid #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: 20px;
        }
        .summary-row {
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
            overflow: hidden;
        }
        .summary-row span:first-child {
            float: left;
        }
        .summary-row span:last-child {
            float: right;
        }
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 13px;
            padding-top: 10px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .signature-area {
            margin-top: 40px;
            overflow: hidden;
        }
        .signature-box {
            width: 45%;
            text-align: center;
            float: left;
        }
        .signature-box:last-child {
            float: right;
        }
        .signature-line {
            margin-top: 60px;
            border-top: 1px solid #333;
            padding-top: 5px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .highlight-box {
            background: #e8f5e9;
            border: 2px solid #4caf50;
            padding: 15px;
            text-align: center;
            margin: 20px 0;
        }
        .highlight-amount {
            font-size: 24px;
            font-weight: bold;
            color: #2e7d32;
        }
        .highlight-label {
            font-size: 11px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">{{ $company->company_name ?? 'Java Indonusa' }}</div>
            <div class="report-title">LAPORAN SETORAN PENAGIH</div>
        </div>

        <!-- Info Penagih -->
        <div class="info-section">
            <table style="width: 60%; border: none;">
                <tr style="border: none;">
                    <td style="border: none; padding: 3px 0; width: 120px;"><strong>Nama Penagih</strong></td>
                    <td style="border: none; padding: 3px 0;">: {{ $collector->name }}</td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding: 3px 0;"><strong>Periode</strong></td>
                    <td style="border: none; padding: 3px 0;">: {{ $period['start']->format('d/m/Y') }} - {{ $period['end']->format('d/m/Y') }}</td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding: 3px 0;"><strong>Dicetak</strong></td>
                    <td style="border: none; padding: 3px 0;">: {{ $generated_at->format('d/m/Y H:i') }}</td>
                </tr>
            </table>
        </div>

        <!-- Highlight: Saldo yang harus disetor -->
        <div class="highlight-box">
            <div class="highlight-label">SALDO YANG HARUS DISETOR</div>
            <div class="highlight-amount">Rp {{ number_format($summary['must_settle'], 0, ',', '.') }}</div>
        </div>

        <!-- Daftar Pembayaran -->
        <div class="section-title">DAFTAR PEMBAYARAN ({{ $payments->count() }} transaksi)</div>
        @if($payments->count() > 0)
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 80px;">Tanggal</th>
                    <th>Pelanggan</th>
                    <th style="width: 80px;">Metode</th>
                    <th class="text-right" style="width: 120px;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $index => $payment)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($payment->created_at)->format('d/m H:i') }}</td>
                    <td>{{ $payment->customer->name ?? '-' }} <span style="color: #999;">({{ $payment->customer->customer_id ?? '-' }})</span></td>
                    <td class="text-center">
                        <span class="badge {{ $payment->payment_method === 'cash' ? 'badge-success' : 'badge-warning' }}">
                            {{ $payment->payment_method === 'cash' ? 'Tunai' : 'Transfer' }}
                        </span>
                    </td>
                    <td class="text-right">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-right">Total Tunai:</th>
                    <th class="text-right">Rp {{ number_format($summary['cash_collected'], 0, ',', '.') }}</th>
                </tr>
                <tr>
                    <th colspan="4" class="text-right">Total Transfer:</th>
                    <th class="text-right">Rp {{ number_format($summary['transfer_collected'], 0, ',', '.') }}</th>
                </tr>
                <tr>
                    <th colspan="4" class="text-right">TOTAL:</th>
                    <th class="text-right">Rp {{ number_format($summary['total_collected'], 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
        @else
        <p style="text-align: center; padding: 20px; color: #666;">Tidak ada pembayaran</p>
        @endif

        <!-- Daftar Pengeluaran -->
        <div class="section-title">DAFTAR PENGELUARAN ({{ $expenses->count() }} item)</div>
        @if($expenses->count() > 0)
        @php
            $categories = [
                'fuel' => 'Bensin',
                'food' => 'Makan',
                'transport' => 'Transport',
                'phone_credit' => 'Pulsa',
                'parking' => 'Parkir',
                'other' => 'Lainnya',
            ];
        @endphp
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 80px;">Tanggal</th>
                    <th style="width: 80px;">Kategori</th>
                    <th>Keterangan</th>
                    <th style="width: 60px;">Status</th>
                    <th class="text-right" style="width: 100px;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expenses as $index => $expense)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($expense->expense_date)->format('d/m/Y') }}</td>
                    <td>{{ $categories[$expense->category] ?? $expense->category }}</td>
                    <td>{{ $expense->description }}</td>
                    <td class="text-center">
                        <span class="badge {{ $expense->status === 'approved' ? 'badge-success' : 'badge-warning' }}">
                            {{ $expense->status === 'approved' ? 'OK' : 'Pending' }}
                        </span>
                    </td>
                    <td class="text-right">Rp {{ number_format($expense->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="text-right">TOTAL PENGELUARAN:</th>
                    <th class="text-right">Rp {{ number_format($summary['total_expense'], 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
        @else
        <p style="text-align: center; padding: 20px; color: #666;">Tidak ada pengeluaran</p>
        @endif

        <!-- Ringkasan Kalkulasi -->
        <div class="summary-box">
            <h3 style="margin-bottom: 10px; font-size: 12px;">KALKULASI SETORAN</h3>
            <div class="summary-row">
                <span>Total Tagihan Tunai</span>
                <span>Rp {{ number_format($settlement['cash_collection'] ?? 0, 0, ',', '.') }}</span>
            </div>
            <div class="summary-row">
                <span>Total Pengeluaran (Approved)</span>
                <span>- Rp {{ number_format($settlement['approved_expense'] ?? 0, 0, ',', '.') }}</span>
            </div>
            @if(($settlement['commission_amount'] ?? 0) > 0)
            <div class="summary-row">
                <span>Komisi ({{ $settlement['commission_rate'] ?? 0 }}%)</span>
                <span>- Rp {{ number_format($settlement['commission_amount'] ?? 0, 0, ',', '.') }}</span>
            </div>
            @endif
            <div class="summary-row">
                <span>SALDO YANG HARUS DISETOR</span>
                <span>Rp {{ number_format($settlement['must_settle'] ?? 0, 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- Tanda Tangan -->
        <div class="signature-area">
            <div class="signature-box">
                <p>Penagih,</p>
                <div class="signature-line">{{ $collector->name }}</div>
            </div>
            <div class="signature-box">
                <p>Diterima oleh,</p>
                <div class="signature-line">(_________________)</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Dokumen ini digenerate otomatis oleh sistem pada {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
