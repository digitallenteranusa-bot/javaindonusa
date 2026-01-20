<?php

namespace App\Exports;

use App\Models\AdminAuditLog;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AdminAuditLogExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = AdminAuditLog::with('admin:id,name,email,role')
            ->orderBy('created_at', 'desc');

        if (!empty($this->filters['admin_id'])) {
            $query->where('admin_id', $this->filters['admin_id']);
        }

        if (!empty($this->filters['module'])) {
            $query->where('module', $this->filters['module']);
        }

        if (!empty($this->filters['action'])) {
            $query->where('action', $this->filters['action']);
        }

        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($this->filters['start_date'])->startOfDay(),
                Carbon::parse($this->filters['end_date'])->endOfDay(),
            ]);
        } elseif (!empty($this->filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($this->filters['start_date'])->startOfDay());
        } elseif (!empty($this->filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($this->filters['end_date'])->endOfDay());
        }

        if (!empty($this->filters['search'])) {
            $query->where('description', 'like', "%{$this->filters['search']}%");
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tanggal',
            'Waktu',
            'Admin',
            'Email Admin',
            'Role',
            'Module',
            'Aksi',
            'Deskripsi',
            'Model',
            'Model ID',
            'IP Address',
            'User Agent',
        ];
    }

    public function map($log): array
    {
        return [
            $log->id,
            $log->created_at?->format('d/m/Y'),
            $log->created_at?->format('H:i:s'),
            $log->admin?->name ?? 'System',
            $log->admin?->email ?? '-',
            $log->admin?->role ?? '-',
            $log->module_label,
            $log->action_label,
            $log->description,
            $log->auditable_type ? class_basename($log->auditable_type) : '-',
            $log->auditable_id ?? '-',
            $log->ip_address ?? '-',
            $log->user_agent ?? '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '3B82F6'],
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
        $title = 'Audit Log';
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $title .= ' ' . date('d-m-Y', strtotime($this->filters['start_date'])) . ' s.d ' . date('d-m-Y', strtotime($this->filters['end_date']));
        }
        return $title;
    }
}
