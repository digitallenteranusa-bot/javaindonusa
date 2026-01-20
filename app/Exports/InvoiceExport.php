<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class InvoiceExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected ?int $year;
    protected ?int $month;
    protected ?string $status;

    public function __construct(?int $year = null, ?int $month = null, ?string $status = null)
    {
        $this->year = $year;
        $this->month = $month;
        $this->status = $status;
    }

    public function collection()
    {
        $query = Invoice::with(['customer', 'customer.area', 'customer.package'])
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->orderBy('invoice_number');

        if ($this->year) {
            $query->where('period_year', $this->year);
        }

        if ($this->month) {
            $query->where('period_month', $this->month);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'No. Invoice',
            'Periode',
            'ID Pelanggan',
            'Nama Pelanggan',
            'Area',
            'Paket',
            'Harga Paket',
            'Biaya Tambahan',
            'Diskon',
            'Total Tagihan',
            'Sudah Dibayar',
            'Sisa',
            'Status',
            'Jatuh Tempo',
            'Tanggal Lunas',
            'Tanggal Dibuat',
        ];
    }

    public function map($invoice): array
    {
        $statusLabels = [
            'pending' => 'Belum Bayar',
            'partial' => 'Sebagian',
            'paid' => 'Lunas',
            'overdue' => 'Jatuh Tempo',
            'cancelled' => 'Dibatalkan',
        ];

        return [
            $invoice->invoice_number,
            $invoice->period_month . '/' . $invoice->period_year,
            $invoice->customer?->customer_id,
            $invoice->customer?->name,
            $invoice->customer?->area?->name,
            $invoice->package_name,
            $invoice->package_price,
            $invoice->additional_charges,
            $invoice->discount,
            $invoice->total_amount,
            $invoice->paid_amount,
            $invoice->remaining_amount,
            $statusLabels[$invoice->status] ?? $invoice->status,
            $invoice->due_date?->format('d/m/Y'),
            $invoice->paid_at?->format('d/m/Y'),
            $invoice->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
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
        $title = 'Invoice';
        if ($this->month && $this->year) {
            $title .= ' ' . $this->month . '-' . $this->year;
        } elseif ($this->year) {
            $title .= ' ' . $this->year;
        }
        return $title;
    }
}
