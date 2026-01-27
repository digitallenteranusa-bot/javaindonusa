<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Bulanan Penagih</title>
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
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
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
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
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
        .badge-danger { background: #f8d7da; color: #721c24; }
        .stat-card {
            display: inline-block;
            width: 24%;
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            font-size: 9px;
            color: #666;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">{{ $company->company_name ?? 'Java Indonusa' }}</div>
            <div class="report-title">LAPORAN BULANAN PENAGIH</div>
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
                    <td style="border: none; padding: 3px 0;">: {{ $month->translatedFormat('F Y') }}</td>
                </tr>
                <tr style="border: none;">
                    <td style="border: none; padding: 3px 0;"><strong>Dicetak</strong></td>
                    <td style="border: none; padding: 3px 0;">: {{ $generated_at->format('d/m/Y H:i') }}</td>
                </tr>
            </table>
        </div>

        <!-- Statistik Ringkasan -->
        <div class="section-title">RINGKASAN BULAN INI</div>
        <table>
            <tr>
                <td class="text-center" style="width: 25%; background: #e8f5e9;">
                    <div style="font-size: 16px; font-weight: bold; color: #2e7d32;">{{ $totals['payment_count'] }}</div>
                    <div style="font-size: 9px; color: #666;">Total Transaksi</div>
                </td>
                <td class="text-center" style="width: 25%; background: #e3f2fd;">
                    <div style="font-size: 14px; font-weight: bold; color: #1565c0;">Rp {{ number_format($totals['payment_total'], 0, ',', '.') }}</div>
                    <div style="font-size: 9px; color: #666;">Total Tagihan</div>
                </td>
                <td class="text-center" style="width: 25%; background: #fff3e0;">
                    <div style="font-size: 14px; font-weight: bold; color: #e65100;">Rp {{ number_format($totals['expense_total'], 0, ',', '.') }}</div>
                    <div style="font-size: 9px; color: #666;">Total Pengeluaran</div>
                </td>
                <td class="text-center" style="width: 25%; background: #f3e5f5;">
                    <div style="font-size: 14px; font-weight: bold; color: #7b1fa2;">Rp {{ number_format($totals['net_collection'], 0, ',', '.') }}</div>
                    <div style="font-size: 9px; color: #666;">Netto</div>
                </td>
            </tr>
        </table>

        <!-- Rekap Harian -->
        <div class="section-title">REKAP HARIAN</div>
        @if(count($dailyStats) > 0)
        <table>
            <thead>
                <tr>
                    <th style="width: 120px;">Tanggal</th>
                    <th class="text-center" style="width: 80px;">Transaksi</th>
                    <th class="text-right">Tagihan</th>
                    <th class="text-right">Pengeluaran</th>
                    <th class="text-right">Netto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dailyStats as $day)
                <tr>
                    <td>{{ $day['date']->format('d/m/Y') }} ({{ $day['date']->translatedFormat('D') }})</td>
                    <td class="text-center">{{ $day['payment_count'] }}</td>
                    <td class="text-right">Rp {{ number_format($day['payment_total'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($day['expense_total'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($day['net'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th class="text-right" colspan="2">TOTAL:</th>
                    <th class="text-right">Rp {{ number_format($totals['payment_total'], 0, ',', '.') }}</th>
                    <th class="text-right">Rp {{ number_format($totals['expense_total'], 0, ',', '.') }}</th>
                    <th class="text-right">Rp {{ number_format($totals['net_collection'], 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
        @else
        <p style="text-align: center; padding: 20px; color: #666;">Tidak ada aktivitas pada bulan ini</p>
        @endif

        <!-- Ringkasan Pengeluaran per Kategori -->
        <div class="section-title">PENGELUARAN PER KATEGORI</div>
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
        @if(isset($expenseSummary['by_category']) && count($expenseSummary['by_category']) > 0)
        <table>
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th class="text-center" style="width: 80px;">Jumlah</th>
                    <th class="text-right" style="width: 150px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expenseSummary['by_category'] as $category => $data)
                <tr>
                    <td>{{ $categories[$category] ?? ucfirst($category) }}</td>
                    <td class="text-center">{{ $data['count'] }}</td>
                    <td class="text-right">Rp {{ number_format($data['total'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th class="text-right" colspan="2">TOTAL PENGELUARAN:</th>
                    <th class="text-right">Rp {{ number_format($expenseSummary['total_expenses'] ?? 0, 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
        @else
        <p style="text-align: center; padding: 20px; color: #666;">Tidak ada pengeluaran pada bulan ini</p>
        @endif

        <!-- Ringkasan Setoran -->
        <div class="summary-box">
            <h3 style="margin-bottom: 10px; font-size: 12px;">RINGKASAN SETORAN BULANAN</h3>
            <div class="summary-row">
                <span>Total Tagihan Tunai</span>
                <span>Rp {{ number_format($settlement['cash_collection'] ?? 0, 0, ',', '.') }}</span>
            </div>
            <div class="summary-row">
                <span>Total Tagihan Transfer</span>
                <span>Rp {{ number_format($settlement['transfer_collection'] ?? 0, 0, ',', '.') }}</span>
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
                <p>Mengetahui,</p>
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
