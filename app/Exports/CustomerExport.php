<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CustomerExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected ?string $status;
    protected ?string $areaId;
    protected ?string $packageId;
    protected ?string $collectorId;
    protected ?string $search;

    public function __construct(
        ?string $status = null,
        ?string $areaId = null,
        ?string $packageId = null,
        ?string $collectorId = null,
        ?string $search = null
    ) {
        $this->status = $status;
        $this->areaId = $areaId;
        $this->packageId = $packageId;
        $this->collectorId = $collectorId;
        $this->search = $search;
    }

    public function query()
    {
        $query = Customer::with(['package:id,name,price', 'area:id,name', 'router:id,name', 'collector:id,name', 'odp:id,name'])
            ->orderBy('name');

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->areaId) {
            $query->where('area_id', $this->areaId);
        }

        if ($this->packageId) {
            $query->where('package_id', $this->packageId);
        }

        if ($this->collectorId) {
            $query->where('collector_id', $this->collectorId);
        }

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('pppoe_username', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID Pelanggan',
            'Nama',
            'Alamat',
            'Kelurahan',
            'Telepon',
            'Telepon Alt',
            'Email',
            'Paket',
            'Harga Paket',
            'Area',
            'Router',
            'ODP',
            'Penagih',
            'Tipe Koneksi',
            'PPPoE Username',
            'IP Address',
            'Status',
            'Total Hutang',
            'Tanggal Gabung',
            'Tanggal Tagih',
            'Mulai Ditagih',
            'Payment Behavior',
            'Diskon Tipe',
            'Diskon Nilai',
            'PPN',
            'Catatan',
        ];
    }

    public function map($customer): array
    {
        $statusLabels = [
            'active' => 'Aktif',
            'isolated' => 'Isolir',
            'suspended' => 'Cuti',
            'terminated' => 'Berhenti',
            'inactive' => 'Nonaktif',
        ];

        return [
            $customer->customer_id,
            $customer->name,
            $customer->address,
            $customer->kelurahan,
            $customer->phone,
            $customer->phone_alt,
            $customer->email,
            $customer->package?->name ?? '-',
            $customer->package?->price ?? 0,
            $customer->area?->name ?? '-',
            $customer->router?->name ?? '-',
            $customer->odp?->name ?? '-',
            $customer->collector?->name ?? '-',
            $customer->connection_type,
            $customer->pppoe_username,
            $customer->ip_address ?? $customer->static_ip,
            $statusLabels[$customer->status] ?? $customer->status,
            $customer->total_debt,
            $customer->join_date?->format('d/m/Y'),
            $customer->billing_date,
            $customer->billing_start_date?->format('d/m/Y'),
            $customer->payment_behavior,
            $customer->discount_type,
            $customer->discount_value,
            $customer->is_taxed ? 'Ya' : 'Tidak',
            $customer->notes,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Data Pelanggan';
    }
}
