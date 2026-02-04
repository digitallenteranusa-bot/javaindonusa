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
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CustomerImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure, WithBatchInserts, WithChunkReading, WithMapping
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
     * Map/transform row data before processing
     */
    public function map($row): array
    {
        return [
            'id_pelanggan' => $row['id_pelanggan'] ?? null,
            'nama' => $row['nama'] ?? null,
            'alamat' => $row['alamat'] ?? null,
            'rt_rw' => isset($row['rt_rw']) ? (string) $row['rt_rw'] : null,
            'kelurahan' => $row['kelurahan'] ?? null,
            'kecamatan' => $row['kecamatan'] ?? null,
            'telepon' => $this->formatPhone($row['telepon'] ?? null),
            'telepon_alternatif' => $this->formatPhone($row['telepon_alternatif'] ?? null),
            'email' => $row['email'] ?? null,
            'nik' => isset($row['nik']) ? (string) $row['nik'] : null,
            'paket' => $row['paket'] ?? null,
            'area' => $row['area'] ?? null,
            'router' => $row['router'] ?? null,
            'penagih' => $row['penagih'] ?? null,
            'pppoe_username' => $row['pppoe_username'] ?? null,
            'pppoe_password' => $row['pppoe_password'] ?? null,
            'ip_address' => $row['ip_address'] ?? null,
            'mac_address' => $row['mac_address'] ?? null,
            'onu_serial' => $row['merk_router'] ?? $row['onu_serial'] ?? null,
            'status' => $row['status'] ?? 'active',
            'hutang' => $row['hutang'] ?? 0,
            'tanggal_gabung' => $row['tanggal_gabung'] ?? null,
            'tanggal_tagih' => $row['tanggal_tagih'] ?? 1,
            'tipe_koneksi' => $row['tipe_koneksi'] ?? 'pppoe',
            'catatan' => $row['catatan'] ?? null,
            'latitude' => $this->formatCoordinate($row['latitude'] ?? null),
            'longitude' => $this->formatCoordinate($row['longitude'] ?? null),
        ];
    }

    /**
     * Format coordinate (latitude/longitude)
     * Handle cases where Excel removes decimal point
     */
    protected function formatCoordinate($value): ?float
    {
        if (empty($value)) return null;

        $value = (float) $value;

        // If value is already in valid range, return as-is
        if ($value >= -180 && $value <= 180) {
            return $value;
        }

        // Check if it's a latitude (should be between -90 and 90)
        // or longitude (should be between -180 and 180)
        // If out of range, it's likely missing decimal point

        // For Indonesian coordinates:
        // Latitude: around -8 to 6 (usually 1-2 digits before decimal, 6 after)
        // Longitude: around 95 to 141 (usually 2-3 digits before decimal, 6 after)

        $absValue = abs($value);
        $sign = $value < 0 ? -1 : 1;

        // Try to detect and fix the decimal position
        if ($absValue > 1000000) {
            // Likely 6 decimal places shifted (e.g., -8124738 should be -8.124738)
            return $sign * ($absValue / 1000000);
        } elseif ($absValue > 100000) {
            // Likely 5 decimal places shifted
            return $sign * ($absValue / 100000);
        } elseif ($absValue > 10000) {
            // Likely 4 decimal places shifted
            return $sign * ($absValue / 10000);
        } elseif ($absValue > 1000) {
            // Likely 3 decimal places shifted
            return $sign * ($absValue / 1000);
        }

        // If still out of range, return null to avoid database error
        if ($value < -180 || $value > 180) {
            return null;
        }

        return $value;
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
            'onu_serial' => $row['merk_router'] ?? $row['onu_serial'] ?? null,
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
            'telepon' => 'nullable|max:20',
            'email' => 'nullable|email|max:100',
        ];
    }

    /**
     * Prepare data before validation
     */
    public function prepareForValidation(array $data): array
    {
        // Convert phone numbers from numeric to string
        if (isset($data['telepon']) && is_numeric($data['telepon'])) {
            $data['telepon'] = $this->formatPhone((string) $data['telepon']);
        }
        if (isset($data['telepon_alternatif']) && is_numeric($data['telepon_alternatif'])) {
            $data['telepon_alternatif'] = $this->formatPhone((string) $data['telepon_alternatif']);
        }

        // Convert NIK from numeric to string
        if (isset($data['nik']) && is_numeric($data['nik'])) {
            $data['nik'] = (string) $data['nik'];
        }

        return $data;
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
    protected function formatPhone($phone): ?string
    {
        if (empty($phone)) return null;

        // Convert to string if numeric
        $phone = (string) $phone;

        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (empty($phone)) return null;

        // Convert 62 prefix to 0
        if (str_starts_with($phone, '62')) {
            $phone = '0' . substr($phone, 2);
        }

        // If phone doesn't start with 0, add it (likely lost leading zero in Excel)
        if (!str_starts_with($phone, '0') && strlen($phone) >= 9) {
            $phone = '0' . $phone;
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
