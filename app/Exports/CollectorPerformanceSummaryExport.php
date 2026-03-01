<?php

namespace App\Exports;

use App\Services\Admin\CollectorPerformanceService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CollectorPerformanceSummaryExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected int $month;
    protected int $year;

    public function __construct(int $month, int $year)
    {
        $this->month = $month;
        $this->year = $year;
    }

    public function collection()
    {
        $service = app(CollectorPerformanceService::class);
        $data = $service->getPerformanceData($this->month, $this->year);

        return collect($data['collectors']);
    }

    public function headings(): array
    {
        return [
            'Nama Penagih',
            'Pelanggan',
            'Target Tagihan',
            'Total Tertagih',
            'Cash',
            'Transfer',
            'Pengeluaran',
            'Setoran',
            'Net Income',
            'Collection Rate (%)',
            'Jumlah Transaksi',
        ];
    }

    public function map($row): array
    {
        return [
            $row['name'],
            $row['customers_count'],
            $row['total_billable'],
            $row['total_collected'],
            $row['cash_collected'],
            $row['transfer_collected'],
            $row['total_expense'],
            $row['cash_deposit'],
            $row['net_income'],
            $row['collection_rate'],
            $row['transactions'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '059669'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return 'Performa ' . $monthNames[$this->month] . ' ' . $this->year;
    }
}
