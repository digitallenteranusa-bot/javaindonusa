<?php

namespace App\Exports;

use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpenseExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected ?string $startDate;
    protected ?string $endDate;
    protected ?string $status;
    protected ?int $userId;
    protected ?string $category;

    public function __construct(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $status = null,
        ?int $userId = null,
        ?string $category = null
    ) {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->status = $status;
        $this->userId = $userId;
        $this->category = $category;
    }

    public function collection()
    {
        $query = Expense::with(['user', 'verifiedBy'])
            ->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($this->startDate) {
            $query->whereDate('expense_date', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('expense_date', '<=', $this->endDate);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        if ($this->category) {
            $query->where('category', $this->category);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Penagih',
            'Kategori',
            'Deskripsi',
            'Jumlah',
            'Status',
            'Diverifikasi Oleh',
            'Tanggal Verifikasi',
            'Catatan',
            'Alasan Ditolak',
        ];
    }

    public function map($expense): array
    {
        static $no = 0;
        $no++;

        $categoryLabels = [
            'transport' => 'Transportasi',
            'meal' => 'Makan',
            'parking' => 'Parkir',
            'toll' => 'Tol',
            'fuel' => 'BBM',
            'maintenance' => 'Perawatan',
            'other' => 'Lainnya',
        ];

        $statusLabels = [
            'pending' => 'Menunggu',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];

        return [
            $no,
            $expense->expense_date?->format('d/m/Y'),
            $expense->user?->name,
            $categoryLabels[$expense->category] ?? $expense->category,
            $expense->description,
            $expense->amount,
            $statusLabels[$expense->status] ?? $expense->status,
            $expense->verifiedBy?->name,
            $expense->verified_at?->format('d/m/Y H:i'),
            $expense->notes,
            $expense->rejection_reason,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Format kolom Jumlah sebagai currency
        $sheet->getStyle('F:F')->getNumberFormat()->setFormatCode('#,##0');

        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EF4444'], // Red untuk expense
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
        $title = 'Pengeluaran';
        if ($this->startDate && $this->endDate) {
            $title .= ' ' . date('d-m-Y', strtotime($this->startDate)) . ' s.d ' . date('d-m-Y', strtotime($this->endDate));
        }
        return $title;
    }
}
