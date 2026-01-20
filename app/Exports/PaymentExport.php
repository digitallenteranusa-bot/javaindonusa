<?php

namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected ?string $startDate;
    protected ?string $endDate;
    protected ?string $paymentMethod;
    protected ?int $collectorId;

    public function __construct(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $paymentMethod = null,
        ?int $collectorId = null
    ) {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->paymentMethod = $paymentMethod;
        $this->collectorId = $collectorId;
    }

    public function collection()
    {
        $query = Payment::with(['customer', 'customer.area', 'collector', 'receivedBy'])
            ->orderBy('created_at', 'desc');

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        if ($this->paymentMethod) {
            $query->where('payment_method', $this->paymentMethod);
        }

        if ($this->collectorId) {
            $query->where('collector_id', $this->collectorId);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'No. Pembayaran',
            'Tanggal',
            'Waktu',
            'ID Pelanggan',
            'Nama Pelanggan',
            'Area',
            'Metode',
            'Jumlah',
            'Alokasi Invoice',
            'Alokasi Hutang',
            'Penagih',
            'Diterima Oleh',
            'Status',
            'Catatan',
        ];
    }

    public function map($payment): array
    {
        $methodLabels = [
            'cash' => 'Tunai',
            'transfer' => 'Transfer',
        ];

        $statusLabels = [
            'success' => 'Sukses',
            'cancelled' => 'Dibatalkan',
        ];

        return [
            $payment->payment_number,
            $payment->created_at?->format('d/m/Y'),
            $payment->created_at?->format('H:i'),
            $payment->customer?->customer_id,
            $payment->customer?->name,
            $payment->customer?->area?->name,
            $methodLabels[$payment->payment_method] ?? $payment->payment_method,
            $payment->amount,
            $payment->allocated_to_invoice,
            $payment->allocated_to_debt,
            $payment->collector?->name,
            $payment->receivedBy?->name,
            $statusLabels[$payment->status] ?? $payment->status,
            $payment->notes,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '22C55E'],
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
        $title = 'Pembayaran';
        if ($this->startDate && $this->endDate) {
            $title .= ' ' . date('d-m-Y', strtotime($this->startDate)) . ' s.d ' . date('d-m-Y', strtotime($this->endDate));
        }
        return $title;
    }
}
