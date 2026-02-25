<?php

namespace App\Exports;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\CollectorPerformanceService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CollectorDetailExport implements WithMultipleSheets
{
    protected int $collectorId;
    protected int $month;
    protected int $year;

    public function __construct(int $collectorId, int $month, int $year)
    {
        $this->collectorId = $collectorId;
        $this->month = $month;
        $this->year = $year;
    }

    public function sheets(): array
    {
        return [
            new CollectorDetailSummarySheet($this->collectorId, $this->month, $this->year),
            new CollectorDetailPaymentsSheet($this->collectorId, $this->month, $this->year),
            new CollectorDetailExpensesSheet($this->collectorId, $this->month, $this->year),
        ];
    }
}

// --- Sheet 1: Ringkasan ---
class CollectorDetailSummarySheet implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    protected int $collectorId;
    protected int $month;
    protected int $year;

    public function __construct(int $collectorId, int $month, int $year)
    {
        $this->collectorId = $collectorId;
        $this->month = $month;
        $this->year = $year;
    }

    public function collection()
    {
        $service = app(CollectorPerformanceService::class);
        $data = $service->getPerformanceData($this->month, $this->year);
        $collector = collect($data['collectors'])->firstWhere('id', $this->collectorId);

        if (!$collector) {
            return collect();
        }

        return collect([(object) [
            'name' => $collector['name'],
            'customers_count' => $collector['customers_count'],
            'total_billable' => $collector['total_billable'],
            'total_collected' => $collector['total_collected'],
            'cash_collected' => $collector['cash_collected'],
            'transfer_collected' => $collector['transfer_collected'],
            'total_expense' => $collector['total_expense'],
            'net_income' => $collector['net_income'],
            'collection_rate' => $collector['collection_rate'],
        ]]);
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
            'Net Income',
            'Collection Rate (%)',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '059669'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }
}

// --- Sheet 2: Pembayaran ---
class CollectorDetailPaymentsSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected int $collectorId;
    protected int $month;
    protected int $year;

    public function __construct(int $collectorId, int $month, int $year)
    {
        $this->collectorId = $collectorId;
        $this->month = $month;
        $this->year = $year;
    }

    public function collection()
    {
        $startDate = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $endDate = Carbon::create($this->year, $this->month, 1)->endOfMonth();

        return Payment::where('collector_id', $this->collectorId)
            ->where('status', 'verified')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('customer')
            ->orderBy('created_at')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'No Pembayaran',
            'Pelanggan',
            'Metode',
            'Jumlah',
        ];
    }

    public function map($row): array
    {
        return [
            $row->created_at->format('d/m/Y'),
            $row->payment_number ?? '-',
            $row->customer->name ?? '-',
            ucfirst($row->payment_method),
            $row->amount,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Pembayaran';
    }
}

// --- Sheet 3: Pengeluaran ---
class CollectorDetailExpensesSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected int $collectorId;
    protected int $month;
    protected int $year;

    public function __construct(int $collectorId, int $month, int $year)
    {
        $this->collectorId = $collectorId;
        $this->month = $month;
        $this->year = $year;
    }

    public function collection()
    {
        $startDate = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $endDate = Carbon::create($this->year, $this->month, 1)->endOfMonth();

        return Expense::where('user_id', $this->collectorId)
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->orderBy('expense_date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Kategori',
            'Deskripsi',
            'Jumlah',
        ];
    }

    public function map($row): array
    {
        return [
            $row->expense_date->format('d/m/Y'),
            $row->category_label,
            $row->description,
            $row->amount,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DC2626'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Pengeluaran';
    }
}
