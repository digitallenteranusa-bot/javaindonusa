<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UnpaidCustomersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected int $month;
    protected int $year;
    protected ?string $areaId;
    protected ?string $collectorId;
    protected ?string $status;
    protected ?string $search;

    public function __construct(int $month, int $year, ?string $areaId = null, ?string $collectorId = null, ?string $status = null, ?string $search = null)
    {
        $this->month = $month;
        $this->year = $year;
        $this->areaId = $areaId;
        $this->collectorId = $collectorId;
        $this->status = $status;
        $this->search = $search;
    }

    public function collection()
    {
        $query = Invoice::with([
            'customer:id,customer_id,name,phone,package_id,area_id,collector_id',
            'customer.package:id,name,price',
            'customer.area:id,name',
            'customer.collector:id,name',
        ])
            ->where('period_month', $this->month)
            ->where('period_year', $this->year)
            ->whereIn('status', ['pending', 'partial', 'overdue']);

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_id', 'like', "%{$search}%");
                    });
            });
        }

        if ($this->areaId) {
            $query->whereHas('customer', fn($q) => $q->where('area_id', $this->areaId));
        }

        if ($this->collectorId) {
            $query->whereHas('customer', fn($q) => $q->where('collector_id', $this->collectorId));
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        $invoices = $query->orderBy('status', 'desc')
            ->orderBy('remaining_amount', 'desc')
            ->get();

        // Calculate overdue months for each invoice
        $invoices->each(function ($invoice) {
            $invoice->overdue_months = Invoice::where('customer_id', $invoice->customer_id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->where(function ($q) {
                    $q->where('period_year', '<', $this->year)
                        ->orWhere(function ($q2) {
                            $q2->where('period_year', $this->year)
                                ->where('period_month', '<=', $this->month);
                        });
                })
                ->count();
        });

        return $invoices;
    }

    public function headings(): array
    {
        return [
            'ID Pelanggan',
            'Nama',
            'Area',
            'Paket',
            'Penagih',
            'Total Tagihan',
            'Terbayar',
            'Sisa',
            'Status',
            'Bulan Nunggak',
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
            $invoice->customer?->area?->name ?? '-',
            $invoice->customer?->package?->name ?? '-',
            $invoice->customer?->collector?->name ?? '-',
            $invoice->total_amount,
            $invoice->paid_amount,
            $invoice->remaining_amount,
            $statusLabels[$invoice->status] ?? $invoice->status,
            $invoice->overdue_months,
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
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DC2626'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return 'Belum Bayar ' . $monthNames[$this->month] . ' ' . $this->year;
    }
}
