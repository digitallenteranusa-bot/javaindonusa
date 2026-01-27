<?php

namespace App\Imports;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Area;
use App\Models\Router;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CustomerImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure, WithBatchInserts, WithChunkReading
{
    use Importable, SkipsErrors, SkipsFailures;

    protected array $packageCache = [];
    protected array $areaCache = [];
    protected array $routerCache = [];
    protected array $collectorCache = [];

    public function __construct()
    {
        // Pre-load lookup data
        $this->packageCache = Package::pluck('id', 'name')->toArray();
        $this->areaCache = Area::pluck('id', 'name')->toArray();
        $this->routerCache = Router::pluck('id', 'name')->toArray();
        $this->collectorCache = User::where('role', 'penagih')->pluck('id', 'name')->toArray();
    }

    /**
     * @param array $row
     * @return Customer|null
     */
    public function model(array $row)
    {
        // Skip if name is empty
        if (empty($row['nama'])) {
            return null;
        }

        // Generate customer_id if not provided
        $customerId = $row['id_pelanggan'] ?? $this->generateCustomerId();

        // Check if customer already exists
        if (Customer::where('customer_id', $customerId)->exists()) {
            return null;
        }

        // Lookup foreign keys
        $packageId = $this->findPackageId($row['paket'] ?? null);
        $areaId = $this->findAreaId($row['area'] ?? null);
        $routerId = $this->findRouterId($row['router'] ?? null);
        $collectorId = $this->findCollectorId($row['penagih'] ?? null);

        // Parse join date
        $joinDate = null;
        if (!empty($row['tanggal_gabung'])) {
            try {
                $joinDate = Carbon::parse($row['tanggal_gabung']);
            } catch (\Exception $e) {
                $joinDate = now();
            }
        }

        return new Customer([
            'customer_id' => $customerId,
            'name' => $row['nama'],
            'address' => $row['alamat'] ?? null,
            'rt_rw' => $row['rt_rw'] ?? null,
            'kelurahan' => $row['kelurahan'] ?? null,
            'kecamatan' => $row['kecamatan'] ?? null,
            'phone' => $this->formatPhone($row['telepon'] ?? null),
            'phone_alt' => $this->formatPhone($row['telepon_alternatif'] ?? null),
            'email' => $row['email'] ?? null,
            'nik' => $row['nik'] ?? null,
            'package_id' => $packageId,
            'area_id' => $areaId,
            'router_id' => $routerId,
            'collector_id' => $collectorId,
            'pppoe_username' => $row['pppoe_username'] ?? null,
            'pppoe_password' => $row['pppoe_password'] ?? null,
            'ip_address' => $row['ip_address'] ?? null,
            'mac_address' => $row['mac_address'] ?? null,
            'onu_serial' => $row['onu_serial'] ?? null,
            'status' => $this->parseStatus($row['status'] ?? 'active'),
            'total_debt' => (float) ($row['hutang'] ?? 0),
            'join_date' => $joinDate ?? now(),
            'billing_date' => (int) ($row['tanggal_tagih'] ?? 1),
            'connection_type' => $row['tipe_koneksi'] ?? 'pppoe',
            'notes' => $row['catatan'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
        ]);
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:100',
            'telepon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
        ];
    }

    /**
     * Custom validation messages
     */
    public function customValidationMessages(): array
    {
        return [
            'nama.required' => 'Nama pelanggan wajib diisi',
            'email.email' => 'Format email tidak valid',
        ];
    }

    /**
     * Generate unique customer ID
     */
    protected function generateCustomerId(): string
    {
        $prefix = 'CUST';
        $date = now()->format('ym');

        $lastCustomer = Customer::where('customer_id', 'like', "{$prefix}{$date}%")
            ->orderBy('customer_id', 'desc')
            ->first();

        if ($lastCustomer) {
            $lastNumber = (int) substr($lastCustomer->customer_id, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s%s%04d', $prefix, $date, $newNumber);
    }

    /**
     * Find package ID by name
     */
    protected function findPackageId(?string $name): ?int
    {
        if (empty($name)) return null;

        // Try exact match first
        if (isset($this->packageCache[$name])) {
            return $this->packageCache[$name];
        }

        // Try case-insensitive search
        foreach ($this->packageCache as $pkgName => $id) {
            if (strtolower($pkgName) === strtolower(trim($name))) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Find area ID by name
     */
    protected function findAreaId(?string $name): ?int
    {
        if (empty($name)) return null;

        if (isset($this->areaCache[$name])) {
            return $this->areaCache[$name];
        }

        foreach ($this->areaCache as $areaName => $id) {
            if (strtolower($areaName) === strtolower(trim($name))) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Find router ID by name
     */
    protected function findRouterId(?string $name): ?int
    {
        if (empty($name)) return null;

        if (isset($this->routerCache[$name])) {
            return $this->routerCache[$name];
        }

        foreach ($this->routerCache as $routerName => $id) {
            if (strtolower($routerName) === strtolower(trim($name))) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Find collector ID by name
     */
    protected function findCollectorId(?string $name): ?int
    {
        if (empty($name)) return null;

        if (isset($this->collectorCache[$name])) {
            return $this->collectorCache[$name];
        }

        foreach ($this->collectorCache as $collectorName => $id) {
            if (strtolower($collectorName) === strtolower(trim($name))) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Format phone number
     */
    protected function formatPhone(?string $phone): ?string
    {
        if (empty($phone)) return null;

        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert 62 prefix to 0
        if (str_starts_with($phone, '62')) {
            $phone = '0' . substr($phone, 2);
        }

        return $phone;
    }

    /**
     * Parse status
     */
    protected function parseStatus(?string $status): string
    {
        $status = strtolower(trim($status ?? ''));

        return match ($status) {
            'aktif', 'active', '1' => 'active',
            'isolir', 'isolated' => 'isolated',
            'suspend', 'suspended' => 'suspended',
            'terminated', 'berhenti' => 'terminated',
            default => 'active',
        };
    }

    /**
     * Batch insert size
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Chunk reading size
     */
    public function chunkSize(): int
    {
        return 100;
    }
}
