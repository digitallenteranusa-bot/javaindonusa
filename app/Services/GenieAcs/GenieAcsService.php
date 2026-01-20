<?php

namespace App\Services\GenieAcs;

use App\Models\Customer;
use App\Models\CustomerDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class GenieAcsService
{
    protected string $baseUrl;
    protected int $timeout;
    protected array $auth;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('genieacs.nbi_url'), '/');
        $this->timeout = config('genieacs.timeout', 30);
        $this->auth = config('genieacs.auth', []);
    }

    // ================================================================
    // HTTP CLIENT
    // ================================================================

    /**
     * Get HTTP client with authentication
     */
    protected function client()
    {
        $http = Http::timeout($this->timeout)
            ->acceptJson();

        if ($this->auth['enabled'] ?? false) {
            $http->withBasicAuth($this->auth['username'], $this->auth['password']);
        }

        return $http;
    }

    /**
     * Make GET request to GenieACS
     */
    protected function get(string $endpoint, array $query = []): ?array
    {
        try {
            $response = $this->client()->get($this->baseUrl . $endpoint, $query);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('GenieACS GET failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('GenieACS request error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Make POST request to GenieACS
     */
    protected function post(string $endpoint, array $data = []): ?array
    {
        try {
            $response = $this->client()
                ->asJson()
                ->post($this->baseUrl . $endpoint, $data);

            if ($response->successful()) {
                return $response->json() ?? ['success' => true];
            }

            Log::error('GenieACS POST failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('GenieACS request error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Make DELETE request to GenieACS
     */
    protected function delete(string $endpoint): bool
    {
        try {
            $response = $this->client()->delete($this->baseUrl . $endpoint);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('GenieACS DELETE error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ================================================================
    // DEVICE OPERATIONS
    // ================================================================

    /**
     * Get all devices from GenieACS
     */
    public function getDevices(array $filter = []): ?array
    {
        $query = [];

        if (!empty($filter)) {
            $query['query'] = json_encode($filter);
        }

        return $this->get('/devices', $query);
    }

    /**
     * Get single device by ID
     */
    public function getDevice(string $deviceId): ?array
    {
        $encoded = urlencode($deviceId);
        return $this->get("/devices/{$encoded}");
    }

    /**
     * Get device by serial number
     */
    public function getDeviceBySerial(string $serial): ?array
    {
        $devices = $this->getDevices([
            'DeviceID.SerialNumber' => $serial,
        ]);

        return $devices[0] ?? null;
    }

    /**
     * Delete device from GenieACS
     */
    public function deleteDevice(string $deviceId): bool
    {
        $encoded = urlencode($deviceId);
        return $this->delete("/devices/{$encoded}");
    }

    // ================================================================
    // TASK OPERATIONS
    // ================================================================

    /**
     * Get pending tasks for a device
     */
    public function getTasks(string $deviceId): ?array
    {
        return $this->get('/tasks', [
            'query' => json_encode(['device' => $deviceId]),
        ]);
    }

    /**
     * Add a task to device
     */
    public function addTask(string $deviceId, string $taskName, array $params = []): ?array
    {
        $encoded = urlencode($deviceId);

        $task = array_merge(['name' => $taskName], $params);

        return $this->post("/devices/{$encoded}/tasks", $task);
    }

    /**
     * Reboot device
     */
    public function rebootDevice(string $deviceId): bool
    {
        $result = $this->addTask($deviceId, 'reboot');
        return $result !== null;
    }

    /**
     * Factory reset device
     */
    public function factoryResetDevice(string $deviceId): bool
    {
        $result = $this->addTask($deviceId, 'factoryReset');
        return $result !== null;
    }

    /**
     * Refresh device parameters
     */
    public function refreshDevice(string $deviceId, string $objectPath = 'Device.'): bool
    {
        $result = $this->addTask($deviceId, 'refreshObject', [
            'objectName' => $objectPath,
        ]);
        return $result !== null;
    }

    /**
     * Get parameter value from device
     */
    public function getParameterValue(string $deviceId, string $parameterPath): ?array
    {
        $result = $this->addTask($deviceId, 'getParameterValues', [
            'parameterNames' => [$parameterPath],
        ]);
        return $result;
    }

    /**
     * Set parameter value on device
     */
    public function setParameterValue(string $deviceId, string $parameterPath, $value): bool
    {
        $result = $this->addTask($deviceId, 'setParameterValues', [
            'parameterValues' => [
                [$parameterPath, $value],
            ],
        ]);
        return $result !== null;
    }

    // ================================================================
    // PRESET OPERATIONS
    // ================================================================

    /**
     * Get all presets
     */
    public function getPresets(): ?array
    {
        return $this->get('/presets');
    }

    /**
     * Create or update preset
     */
    public function setPreset(string $name, array $config): bool
    {
        $encoded = urlencode($name);
        $response = $this->client()
            ->asJson()
            ->put($this->baseUrl . "/presets/{$encoded}", $config);

        return $response->successful();
    }

    // ================================================================
    // SYNC OPERATIONS
    // ================================================================

    /**
     * Sync all devices from GenieACS to local database
     */
    public function syncAllDevices(): array
    {
        $devices = $this->getDevices();

        if ($devices === null) {
            return [
                'success' => false,
                'message' => 'Failed to fetch devices from GenieACS',
            ];
        }

        $synced = 0;
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($devices as $device) {
            try {
                $result = $this->syncDevice($device);

                if ($result['created']) {
                    $created++;
                } else {
                    $updated++;
                }
                $synced++;
            } catch (\Exception $e) {
                $errors[] = [
                    'device_id' => $device['_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Update offline status for devices not in GenieACS
        $this->updateOfflineDevices($devices);

        Log::info('GenieACS sync completed', [
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
            'errors' => count($errors),
        ]);

        return [
            'success' => true,
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Sync single device to database
     */
    public function syncDevice(array $device): array
    {
        $deviceId = $device['_id'] ?? null;

        if (!$deviceId) {
            throw new \Exception('Device ID not found');
        }

        $data = $this->extractDeviceData($device);

        // Try to find existing device
        $customerDevice = CustomerDevice::where('device_id', $deviceId)->first();

        // Try to match with customer by serial number
        $customerId = $customerDevice?->customer_id;

        if (!$customerId && $data['serial_number']) {
            $customer = Customer::where('onu_serial', $data['serial_number'])->first();
            $customerId = $customer?->id;
        }

        // Update or create device record
        if ($customerDevice) {
            $customerDevice->update(array_merge($data, [
                'customer_id' => $customerId ?? $customerDevice->customer_id,
            ]));

            return ['device' => $customerDevice, 'created' => false];
        }

        // Only create if we have a customer match
        if ($customerId) {
            $customerDevice = CustomerDevice::create(array_merge($data, [
                'customer_id' => $customerId,
                'device_id' => $deviceId,
            ]));

            return ['device' => $customerDevice, 'created' => true];
        }

        // Device exists in GenieACS but not matched to customer
        Log::info('Unmatched GenieACS device', [
            'device_id' => $deviceId,
            'serial' => $data['serial_number'],
        ]);

        throw new \Exception('No matching customer found');
    }

    /**
     * Extract device data from GenieACS format
     */
    protected function extractDeviceData(array $device): array
    {
        $params = config('genieacs.sync_parameters', []);
        $data = [];

        foreach ($params as $field => $path) {
            $data[$field] = $this->extractParameter($device, $path);
        }

        // Parse last inform time
        $lastInform = $device['_lastInform'] ?? null;
        if ($lastInform) {
            $data['last_inform'] = Carbon::parse($lastInform);
        }

        // Check online status
        $offlineThreshold = config('genieacs.thresholds.offline_minutes', 30);
        $data['is_online'] = isset($data['last_inform']) &&
            $data['last_inform']->diffInMinutes(now()) <= $offlineThreshold;

        // Store tags and raw data
        $data['tags'] = $device['_tags'] ?? [];
        $data['raw_data'] = $device;

        return $data;
    }

    /**
     * Extract parameter value from GenieACS device structure
     */
    protected function extractParameter(array $device, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $device;

        foreach ($parts as $part) {
            if (isset($current[$part])) {
                $current = $current[$part];
            } else {
                return null;
            }
        }

        // GenieACS stores values in _value key
        if (is_array($current) && isset($current['_value'])) {
            return $current['_value'];
        }

        return is_array($current) ? null : $current;
    }

    /**
     * Mark devices as offline if not in GenieACS response
     */
    protected function updateOfflineDevices(array $genieDevices): void
    {
        $genieDeviceIds = collect($genieDevices)->pluck('_id')->filter()->toArray();

        CustomerDevice::whereNotIn('device_id', $genieDeviceIds)
            ->where('is_online', true)
            ->update(['is_online' => false]);
    }

    // ================================================================
    // WIFI MANAGEMENT
    // ================================================================

    /**
     * Change WiFi SSID
     */
    public function changeWifiSsid(string $deviceId, string $newSsid): bool
    {
        return $this->setParameterValue(
            $deviceId,
            'Device.WiFi.SSID.1.SSID',
            $newSsid
        );
    }

    /**
     * Change WiFi Password
     */
    public function changeWifiPassword(string $deviceId, string $newPassword): bool
    {
        return $this->setParameterValue(
            $deviceId,
            'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',
            $newPassword
        );
    }

    /**
     * Enable/Disable WiFi
     */
    public function setWifiEnabled(string $deviceId, bool $enabled): bool
    {
        return $this->setParameterValue(
            $deviceId,
            'Device.WiFi.Radio.1.Enable',
            $enabled ? '1' : '0'
        );
    }

    // ================================================================
    // FIRMWARE UPDATE
    // ================================================================

    /**
     * Download firmware to device (TR-069 Download task)
     *
     * @param string $deviceId Device ID
     * @param string $fileUrl URL of the firmware file to download
     * @param string $fileType File type (1 = Firmware, 2 = Web Content, 3 = Vendor Config)
     * @return array|null Task result or null on failure
     */
    public function downloadFirmware(string $deviceId, string $fileUrl, string $fileType = '1'): ?array
    {
        return $this->addTask($deviceId, 'download', [
            'fileType' => $fileType,
            'url' => $fileUrl,
        ]);
    }

    /**
     * Get available firmware files
     */
    public function getFiles(): ?array
    {
        return $this->get('/files');
    }

    /**
     * Get file by ID
     */
    public function getFile(string $fileId): ?array
    {
        $encoded = urlencode($fileId);
        return $this->get("/files/{$encoded}");
    }

    /**
     * Upload firmware file to GenieACS
     */
    public function uploadFile(string $filename, string $content, string $fileType = '1 Firmware Upgrade Image', array $metadata = []): bool
    {
        try {
            $encoded = urlencode($filename);

            $response = $this->client()
                ->withHeaders([
                    'Content-Type' => 'application/octet-stream',
                    'fileType' => $fileType,
                    'oui' => $metadata['oui'] ?? '',
                    'productClass' => $metadata['productClass'] ?? '',
                    'version' => $metadata['version'] ?? '',
                ])
                ->withBody($content, 'application/octet-stream')
                ->put($this->baseUrl . "/files/{$encoded}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('GenieACS file upload error', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete firmware file from GenieACS
     */
    public function deleteFile(string $fileId): bool
    {
        $encoded = urlencode($fileId);
        return $this->delete("/files/{$encoded}");
    }

    // ================================================================
    // DIAGNOSTICS
    // ================================================================

    /**
     * Run ping diagnostic
     */
    public function runPingDiagnostic(string $deviceId, string $host): bool
    {
        $this->setParameterValue(
            $deviceId,
            'Device.IP.Diagnostics.IPPing.Host',
            $host
        );

        return $this->setParameterValue(
            $deviceId,
            'Device.IP.Diagnostics.IPPing.DiagnosticsState',
            'Requested'
        );
    }

    /**
     * Check GenieACS connection
     */
    public function checkConnection(): array
    {
        try {
            $response = $this->client()->get($this->baseUrl . '/devices?limit=1');

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'Connected' : 'Connection failed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 0,
                'message' => $e->getMessage(),
            ];
        }
    }
}
