<?php

namespace App\Imports;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Area;
use App\Models\Router;
use App\Models\User;
use App\Models\Odp;
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
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CustomerImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure, WithBatchInserts, WithChunkReading, WithMapping, SkipsEmptyRows
{
    use Importable, SkipsErrors, SkipsFailures;

    protected array $packageCache = [];
    protected array $areaCache = [];
    protected array $routerCache = [];
    protected array $collectorCache = [];
    protected array $odpCacheByName = [];
    protected array $odpCacheByCode = [];
    protected int $customerIdCounter = 0;
    protected string $customerIdPrefix = '';

    public function __construct()
    {
        // Pre-load lookup data
        $this->packageCache = Package::pluck('id', 'name')->toArray();
        $this->areaCache = Area::pluck('id', 'name')->toArray();
        $this->routerCache = Router::pluck('id', 'name')->toArray();
        $this->collectorCache = User::where('role', 'penagih')->pluck('id', 'name')->toArray();

        // ODP cache by name and code
        $odps = Odp::all(['id', 'name', 'code']);
        foreach ($odps as $odp) {
            $this->odpCacheByName[$odp->name] = $odp->id;
            $this->odpCacheByCode[$odp->code] = $odp->id;
        }

        // Initialize customer ID counter
        $this->customerIdPrefix = 'CUST' . now()->format('ym');
        $lastCustomer = Customer::where('customer_id', 'like', "{$this->customerIdPrefix}%")
            ->orderBy('customer_id', 'desc')
            ->first();

        if ($lastCustomer) {
            $this->customerIdCounter = (int) substr($lastCustomer->customer_id, -4);
        } else {
            $this->customerIdCounter = 0;
        }
    }

    /**
     * Map/transform row data before processing
     */
    public function map($row): array
    {
        return [
            'nama' => $row['nama'] ?? null,
            'alamat' => $row['alamat'] ?? null,
            'kelurahan' => $row['kelurahan'] ?? null,
            'telepon' => $this->formatPhone($row['telepon'] ?? null),
            'paket' => $row['paket'] ?? null,
            'area' => $row['area'] ?? null,
            'router' => $row['router'] ?? null,
            'odp' => $row['odp'] ?? null,
            'penagih' => $row['penagih'] ?? null,
            'tipe_koneksi' => $this->parseConnectionType($row['tipe_koneksi'] ?? null),
            'pppoe_username' => $row['pppoe_username'] ?? null,
            'static_ip' => $row['static_ip'] ?? null,
            'status' => $row['status'] ?? 'active',
            'hutang' => $row['hutang'] ?? 0,
            'tanggal_gabung' => $row['tanggal_gabung'] ?? null,
            'tanggal_tagih' => $row['tanggal_tagih'] ?? 1,
            'rapel_bulan' => $row['rapel_bulan'] ?? 3,
            'mulai_ditagih' => $row['mulai_ditagih'] ?? null,
            'diskon_tipe' => $this->parseDiscountType($row['diskon_tipe'] ?? null),
            'diskon_nilai' => $row['diskon_nilai'] ?? 0,
            'ppn' => $row['ppn'] ?? null,
        ];
    }

    /**
     * Parse connection type from various formats
     */
    protected function parseConnectionType(?string $type): string
    {
        if (empty($type)) {
            return 'pppoe';
        }

        $type = strtolower(trim($type));

        // Check for static IP indicators
        if (in_array($type, ['static', 'static ip', 'staticip', 'static_ip', 'statis', 'ip statis'])) {
            return 'static';
        }

        // Check for pppoe indicators
        if (in_array($type, ['pppoe', 'ppp', 'ppoe'])) {
            return 'pppoe';
        }

        // Default to pppoe if unrecognized
        return 'pppoe';
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

        // Generate customer_id (always auto-generate)
        $customerId = $this->generateCustomerId();

        // Check if customer already exists
        if (Customer::where('customer_id', $customerId)->exists()) {
            return null;
        }

        // Lookup foreign keys
        $packageId = $this->findPackageId($row['paket'] ?? null);
        $areaId = $this->findAreaId($row['area'] ?? null);
        $routerId = $this->findRouterId($row['router'] ?? null);
        $collectorId = $this->findCollectorId($row['penagih'] ?? null);

        // Determine connection type
        // If static_ip has value, assume static connection
        $connectionType = $row['tipe_koneksi'] ?? 'pppoe';
        if (!empty($row['static_ip']) && empty($row['pppoe_username'])) {
            $connectionType = 'static';
        }

        // ODP lookup - only for PPPoE connection type
        $odpId = null;
        if ($connectionType === 'pppoe' && !empty($row['odp'])) {
            $odpId = $this->findOdpId($row['odp']);
        }

        // Parse join date (various formats)
        $joinDate = $this->parseDate($row['tanggal_gabung'] ?? null);

        // Determine rapel status based on rapel_bulan
        $rapelMonths = (int) ($row['rapel_bulan'] ?? 3);
        $isRapel = $rapelMonths > 0;

        return new Customer([
            'customer_id' => $customerId,
            'name' => $row['nama'],
            'address' => $row['alamat'] ?? null,
            'kelurahan' => $row['kelurahan'] ?? null,
            'kecamatan' => 'Pule', // Default kecamatan
            'phone' => $this->formatPhone($row['telepon'] ?? null),
            'package_id' => $packageId,
            'area_id' => $areaId,
            'router_id' => $routerId,
            'odp_id' => $odpId,
            'collector_id' => $collectorId,
            'connection_type' => $connectionType,
            'pppoe_username' => $row['pppoe_username'] ?? null,
            'pppoe_password' => 'client001', // Default password
            'static_ip' => $row['static_ip'] ?? null,
            'status' => $this->parseStatus($row['status'] ?? 'active'),
            'billing_type' => 'prepaid', // Default: bayar di muka
            'total_debt' => (float) ($row['hutang'] ?? 0),
            'join_date' => $joinDate ?? now(),
            'billing_date' => (int) ($row['tanggal_tagih'] ?? 1),
            'billing_start_date' => !empty($row['mulai_ditagih']) ? $this->parseDate($row['mulai_ditagih']) : null,
            'payment_behavior' => $isRapel ? 'rapel' : 'regular',
            'is_rapel' => $isRapel,
            'rapel_months' => $rapelMonths,
            'discount_type' => $row['diskon_tipe'] ?? 'none',
            'discount_value' => (float) ($row['diskon_nilai'] ?? 0),
            'is_taxed' => $this->parseBooleanValue($row['ppn'] ?? null),
        ]);
    }

    /**
     * Validation rules
     * Note: nama validation moved to model() to allow skipping empty rows
     */
    public function rules(): array
    {
        return [
            'nama' => 'nullable|string|max:100',
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
     * Uses counter to avoid duplicates in batch imports
     */
    protected function generateCustomerId(): string
    {
        $this->customerIdCounter++;
        return sprintf('%s%04d', $this->customerIdPrefix, $this->customerIdCounter);
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
     * Find ODP ID by name or code
     */
    protected function findOdpId(?string $nameOrCode): ?int
    {
        if (empty($nameOrCode)) return null;

        $nameOrCode = trim($nameOrCode);

        // Try exact match by code first
        if (isset($this->odpCacheByCode[$nameOrCode])) {
            return $this->odpCacheByCode[$nameOrCode];
        }

        // Try exact match by name
        if (isset($this->odpCacheByName[$nameOrCode])) {
            return $this->odpCacheByName[$nameOrCode];
        }

        // Try case-insensitive search by code
        foreach ($this->odpCacheByCode as $code => $id) {
            if (strtolower($code) === strtolower($nameOrCode)) {
                return $id;
            }
        }

        // Try case-insensitive search by name
        foreach ($this->odpCacheByName as $name => $id) {
            if (strtolower($name) === strtolower($nameOrCode)) {
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
     * Parse date from various formats
     */
    protected function parseDate($value): ?\Carbon\Carbon
    {
        if (empty($value)) {
            return now();
        }

        // If it's already a Carbon/DateTime instance
        if ($value instanceof \Carbon\Carbon || $value instanceof \DateTime) {
            return Carbon::instance($value);
        }

        // If it's a numeric value (Excel serial date)
        if (is_numeric($value)) {
            // Excel serial date: days since 1900-01-01
            // Excel incorrectly considers 1900 as a leap year, so we subtract 2
            try {
                $unixTimestamp = ($value - 25569) * 86400;
                if ($unixTimestamp > 0) {
                    return Carbon::createFromTimestamp($unixTimestamp);
                }
            } catch (\Exception $e) {
                // Fall through to string parsing
            }
        }

        $value = trim((string) $value);

        // Try various date formats
        $formats = [
            'd/m/Y',      // 15/12/2025
            'd-m-Y',      // 15-12-2025
            'd.m.Y',      // 15.12.2025
            'Y-m-d',      // 2025-12-15
            'Y/m/d',      // 2025/12/15
            'd/m/y',      // 15/12/25
            'd-m-y',      // 15-12-25
            'd M Y',      // 15 Dec 2025
            'd F Y',      // 15 December 2025
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->year > 1970) {
                    return $date;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Last resort: try Carbon::parse
        try {
            $date = Carbon::parse($value);
            if ($date && $date->year > 1970) {
                return $date;
            }
        } catch (\Exception $e) {
            // Return current date if all parsing fails
        }

        return now();
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
     * Parse billing type
     */
    protected function parseBillingType(?string $type): string
    {
        $type = strtolower(trim($type ?? ''));

        return match ($type) {
            'prepaid', 'prabayar' => 'prepaid',
            'postpaid', 'pascabayar' => 'postpaid',
            default => 'postpaid',
        };
    }

    /**
     * Parse payment behavior
     */
    protected function parsePaymentBehavior(?string $behavior): string
    {
        $behavior = strtolower(trim($behavior ?? ''));

        return match ($behavior) {
            'regular', 'reguler' => 'regular',
            'rapel' => 'rapel',
            'problematic', 'bermasalah' => 'problematic',
            default => 'regular',
        };
    }

    /**
     * Parse discount type
     */
    protected function parseDiscountType(?string $type): string
    {
        if (empty($type)) return 'none';

        $type = strtolower(trim($type));

        return match ($type) {
            'nominal', 'rp', 'rupiah' => 'nominal',
            'percentage', 'persen', '%', 'persentase' => 'percentage',
            default => 'none',
        };
    }

    /**
     * Parse boolean value from various formats
     */
    protected function parseBooleanValue($value): bool
    {
        if (empty($value)) return false;

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'ya', 'yes', 'y', 'aktif', 'active']);
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
