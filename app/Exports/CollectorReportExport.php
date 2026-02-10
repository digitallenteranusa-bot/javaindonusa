<?php

namespace App\Exports;

use App\Models\Payment;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class CollectorReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
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
        $startDate = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $endDate = Carbon::create($this->year, $this->month, 1)->endOfMonth();

        $collectors = User::where('role', 'penagih')
            ->where('is_active', true)
            ->get();

        $result = collect();

        foreach ($collectors as $collector) {
            $payments = Payment::where('collector_id', $collector->id)
                ->where('status', 'verified')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $result->push((object) [
                'name' => $collector->name,
                'customers_count' => $collector->assignedCustomers()->where('status', 'active')->count(),
                'total_collected' => $payments->sum('amount'),
                'cash_collected' => $payments->where('payment_method', 'cash')->sum('amount'),
                'transfer_collected' => $payments->where('payment_method', 'transfer')->sum('amount'),
                'transactions' => $payments->count(),
            ]);
        }

        return $result->sortByDesc('total_collected');
    }

    public function headings(): array
    {
        return [
            'Nama Penagih',
            'Jumlah Pelanggan',
            'Total Tagihan Masuk',
            'Cash',
            'Transfer',
            'Jumlah Transaksi',
        ];
    }

    public function map($row): array
    {
        return [
            $row->name,
            $row->customers_count,
            $row->total_collected,
            $row->cash_collected,
            $row->transfer_collected,
            $row->transactions,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '8B5CF6'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return 'Penagih ' . $monthNames[$this->month] . ' ' . $this->year;
    }
}
