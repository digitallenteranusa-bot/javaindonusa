<?php

namespace App\Exports;

use App\Models\Invoice;
use App\Models\User;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CustomerDebtByCollectorExport implements WithMultipleSheets
{
    protected int $month;
    protected int $year;
    protected ?string $collectorId;

    public function __construct(int $month, int $year, ?string $collectorId = null)
    {
        $this->month = $month;
        $this->year = $year;
        $this->collectorId = $collectorId;
    }

    public function sheets(): array
    {
        $collectors = User::where('role', 'penagih')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($this->collectorId) {
            $collectors = $collectors->where('id', $this->collectorId);
        }

        $sheets = [];
        $sheets[] = new CustomerDebtSummarySheet($this->month, $this->year, $this->collectorId);

        foreach ($collectors as $collector) {
            $sheets[] = new CustomerDebtCollectorSheet($collector, $this->month, $this->year);
        }

        return $sheets;
    }
}

class CustomerDebtSummarySheet implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    protected int $month;
    protected int $year;
    protected ?string $collectorId;

    public function __construct(int $month, int $year, ?string $collectorId = null)
    {
        $this->month = $month;
        $this->year = $year;
        $this->collectorId = $collectorId;
    }

    public function collection()
    {
        $collectors = User::where('role', 'penagih')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($this->collectorId) {
            $collectors = $collectors->where('id', $this->collectorId);
        }

        $result = collect();

        foreach ($collectors as $collector) {
            $invoices = Invoice::whereHas('customer', fn($q) => $q->where('collector_id', $collector->id))
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->where(function ($q) {
                    $q->where('period_year', '<', $this->year)
                        ->orWhere(function ($q2) {
                            $q2->where('period_year', $this->year)
                                ->where('period_month', '<=', $this->month);
                        });
                })
                ->get();

            $customerCount = $invoices->groupBy('customer_id')->count();
            $totalDebt = $invoices->sum('remaining_amount');

            $result->push((object) [
                'name' => $collector->name,
                'customer_count' => $customerCount,
                'invoice_count' => $invoices->count(),
                'total_debt' => $totalDebt,
            ]);
        }

        return $result->sortByDesc('total_debt');
    }

    public function headings(): array
    {
        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return [
            ["Laporan Piutang Pelanggan per Penagih"],
            ["Periode: s/d {$monthNames[$this->month]} {$this->year}"],
            [],
            ['Nama Penagih', 'Jumlah Pelanggan', 'Jumlah Invoice', 'Total Piutang'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:D1');
        $sheet->mergeCells('A2:D2');

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true, 'size' => 11]],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '7C3AED'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }
}

class CustomerDebtCollectorSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected User $collector;
    protected int $month;
    protected int $year;

    public function __construct(User $collector, int $month, int $year)
    {
        $this->collector = $collector;
        $this->month = $month;
        $this->year = $year;
    }

    public function collection()
    {
        $invoices = Invoice::with([
            'customer:id,customer_id,name,phone,address,package_id,area_id,collector_id',
            'customer.package:id,name,price',
            'customer.area:id,name',
        ])
            ->whereHas('customer', fn($q) => $q->where('collector_id', $this->collector->id))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where(function ($q) {
                $q->where('period_year', '<', $this->year)
                    ->orWhere(function ($q2) {
                        $q2->where('period_year', $this->year)
                            ->where('period_month', '<=', $this->month);
                    });
            })
            ->orderBy('customer_id')
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        return $invoices;
    }

    public function headings(): array
    {
        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return [
            ["Penagih: {$this->collector->name}"],
            ["Periode piutang: s/d {$monthNames[$this->month]} {$this->year}"],
            [],
            ['ID Pelanggan', 'Nama', 'Telepon', 'Area', 'Paket', 'Periode', 'Total Tagihan', 'Terbayar', 'Sisa Hutang', 'Status'],
        ];
    }

    public function map($invoice): array
    {
        $statusLabels = [
            'pending' => 'Belum Bayar',
            'partial' => 'Sebagian',
            'overdue' => 'Jatuh Tempo',
        ];

        return [
            $invoice->customer?->customer_id,
            $invoice->customer?->name,
            $invoice->customer?->phone,
            $invoice->customer?->area?->name ?? '-',
            $invoice->customer?->package?->name ?? '-',
            $invoice->period_month . '/' . $invoice->period_year,
            $invoice->total_amount,
            $invoice->paid_amount,
            $invoice->remaining_amount,
            $statusLabels[$invoice->status] ?? $invoice->status,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:J1');
        $sheet->mergeCells('A2:J2');

        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            2 => ['font' => ['italic' => true, 'size' => 10]],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '8B5CF6'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        $name = substr($this->collector->name, 0, 28);
        return $name;
    }
}
