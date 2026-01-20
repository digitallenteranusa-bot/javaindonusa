# Alur Integrasi Sistem Billing ISP
## Java Indonusa

---

## 1. Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────────────┐
│                        BILLING SYSTEM (Laravel)                      │
├─────────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌────────────┐ │
│  │   Web UI    │  │   REST API  │  │  Scheduler  │  │   Queue    │ │
│  │  (Inertia)  │  │  (Sanctum)  │  │   (Cron)    │  │  (Redis)   │ │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └─────┬──────┘ │
│         │                │                │                │        │
│         └────────────────┴────────────────┴────────────────┘        │
│                                   │                                  │
│                          ┌───────┴───────┐                          │
│                          │   Services    │                          │
│                          └───────┬───────┘                          │
└──────────────────────────────────┼──────────────────────────────────┘
                                   │
           ┌───────────────────────┼───────────────────────┐
           │                       │                       │
           ▼                       ▼                       ▼
   ┌───────────────┐      ┌───────────────┐      ┌───────────────┐
   │   MIKROTIK    │      │   GENIEACS    │      │    GATEWAY    │
   │   Router API  │      │   (TR-069)    │      │   (WA/SMS)    │
   │   Port 8728   │      │   Port 7557   │      │               │
   └───────┬───────┘      └───────┬───────┘      └───────────────┘
           │                      │
           ▼                      ▼
   ┌───────────────┐      ┌───────────────┐
   │   PPPoE/DHCP  │      │   ONT/Router  │
   │   Pelanggan   │      │   Pelanggan   │
   └───────────────┘      └───────────────┘
```

---

## 2. Integrasi Mikrotik API

### 2.1 Konfigurasi Koneksi

```php
// config/mikrotik.php
return [
    'default_port' => env('MIKROTIK_PORT', 8728),
    'timeout' => env('MIKROTIK_TIMEOUT', 10),
    'attempts' => env('MIKROTIK_ATTEMPTS', 3),
    'ssl' => env('MIKROTIK_SSL', false),

    'profiles' => [
        'default' => 'default',
        'isolated' => 'isolated',  // Profile dengan bandwidth 0
    ],
];
```

### 2.2 Service Mikrotik

```php
// app/Services/MikrotikService.php
namespace App\Services;

use App\Models\Router;
use App\Models\Customer;
use RouterOS\Client;
use RouterOS\Query;

class MikrotikService
{
    protected $client;
    protected $router;

    /**
     * Koneksi ke Router Mikrotik
     */
    public function connect(Router $router): self
    {
        $this->router = $router;

        $this->client = new Client([
            'host' => $router->ip_address,
            'user' => $router->api_username,
            'pass' => decrypt($router->api_password),
            'port' => $router->api_port,
            'timeout' => config('mikrotik.timeout'),
        ]);

        return $this;
    }

    /**
     * ISOLIR: Nonaktifkan akses pelanggan
     */
    public function isolateCustomer(Customer $customer): array
    {
        $this->connect($customer->router);

        if ($customer->connection_type === 'pppoe') {
            return $this->isolatePPPoE($customer);
        }

        return $this->isolateStatic($customer);
    }

    /**
     * Isolir PPPoE - Ubah profile ke isolated
     */
    protected function isolatePPPoE(Customer $customer): array
    {
        // 1. Cari secret PPPoE berdasarkan username
        $query = new Query('/ppp/secret/print');
        $query->where('name', $customer->pppoe_username);
        $secrets = $this->client->query($query)->read();

        if (empty($secrets)) {
            throw new \Exception("PPPoE user tidak ditemukan: {$customer->pppoe_username}");
        }

        // 2. Update profile ke isolated
        $secretId = $secrets[0]['.id'];
        $query = new Query('/ppp/secret/set');
        $query->equal('.id', $secretId);
        $query->equal('profile', config('mikrotik.profiles.isolated'));
        $this->client->query($query)->read();

        // 3. Kick active session (disconnect paksa)
        $this->disconnectActiveSession($customer->pppoe_username);

        return [
            'success' => true,
            'message' => 'Pelanggan berhasil diisolir',
            'action' => 'isolate',
        ];
    }

    /**
     * Isolir Static IP - Disable ARP/route
     */
    protected function isolateStatic(Customer $customer): array
    {
        // Disable ARP entry
        $query = new Query('/ip/arp/print');
        $query->where('address', $customer->static_ip);
        $arps = $this->client->query($query)->read();

        if (!empty($arps)) {
            $query = new Query('/ip/arp/disable');
            $query->equal('.id', $arps[0]['.id']);
            $this->client->query($query)->read();
        }

        return [
            'success' => true,
            'message' => 'Pelanggan static IP berhasil diisolir',
            'action' => 'isolate',
        ];
    }

    /**
     * BUKA AKSES: Aktifkan kembali akses pelanggan
     */
    public function openAccess(Customer $customer): array
    {
        $this->connect($customer->router);

        if ($customer->connection_type === 'pppoe') {
            return $this->openPPPoE($customer);
        }

        return $this->openStatic($customer);
    }

    /**
     * Buka akses PPPoE - Kembalikan profile normal
     */
    protected function openPPPoE(Customer $customer): array
    {
        // 1. Cari secret PPPoE
        $query = new Query('/ppp/secret/print');
        $query->where('name', $customer->pppoe_username);
        $secrets = $this->client->query($query)->read();

        if (empty($secrets)) {
            throw new \Exception("PPPoE user tidak ditemukan");
        }

        // 2. Update ke profile sesuai paket
        $profile = $this->getProfileForPackage($customer->package);

        $query = new Query('/ppp/secret/set');
        $query->equal('.id', $secrets[0]['.id']);
        $query->equal('profile', $profile);
        $this->client->query($query)->read();

        return [
            'success' => true,
            'message' => 'Akses pelanggan berhasil dibuka',
            'action' => 'open_access',
        ];
    }

    /**
     * Disconnect active PPPoE session
     */
    protected function disconnectActiveSession(string $username): void
    {
        $query = new Query('/ppp/active/print');
        $query->where('name', $username);
        $sessions = $this->client->query($query)->read();

        foreach ($sessions as $session) {
            $query = new Query('/ppp/active/remove');
            $query->equal('.id', $session['.id']);
            $this->client->query($query)->read();
        }
    }

    /**
     * Tambah user PPPoE baru
     */
    public function addPPPoEUser(Customer $customer): array
    {
        $this->connect($customer->router);

        $profile = $this->getProfileForPackage($customer->package);

        $query = new Query('/ppp/secret/add');
        $query->equal('name', $customer->pppoe_username);
        $query->equal('password', decrypt($customer->pppoe_password));
        $query->equal('profile', $profile);
        $query->equal('service', 'pppoe');
        $query->equal('comment', "ID: {$customer->customer_id} - {$customer->name}");

        $this->client->query($query)->read();

        return [
            'success' => true,
            'message' => 'User PPPoE berhasil ditambahkan',
        ];
    }

    /**
     * Hapus user PPPoE
     */
    public function removePPPoEUser(Customer $customer): array
    {
        $this->connect($customer->router);

        $query = new Query('/ppp/secret/print');
        $query->where('name', $customer->pppoe_username);
        $secrets = $this->client->query($query)->read();

        if (!empty($secrets)) {
            // Disconnect dulu jika aktif
            $this->disconnectActiveSession($customer->pppoe_username);

            // Hapus secret
            $query = new Query('/ppp/secret/remove');
            $query->equal('.id', $secrets[0]['.id']);
            $this->client->query($query)->read();
        }

        return [
            'success' => true,
            'message' => 'User PPPoE berhasil dihapus',
        ];
    }

    /**
     * Sinkronisasi profile bandwidth dengan paket
     */
    public function syncProfiles(Router $router): array
    {
        $this->connect($router);
        $packages = \App\Models\Package::where('is_active', true)->get();
        $synced = [];

        foreach ($packages as $package) {
            $profileName = $this->getProfileName($package);
            $rateLimit = $this->formatRateLimit($package);

            // Cek apakah profile sudah ada
            $query = new Query('/ppp/profile/print');
            $query->where('name', $profileName);
            $existing = $this->client->query($query)->read();

            if (empty($existing)) {
                // Buat profile baru
                $query = new Query('/ppp/profile/add');
                $query->equal('name', $profileName);
                $query->equal('rate-limit', $rateLimit);
                $query->equal('local-address', 'pool-local');
                $query->equal('remote-address', 'pool-customer');
                $this->client->query($query)->read();
            } else {
                // Update rate-limit
                $query = new Query('/ppp/profile/set');
                $query->equal('.id', $existing[0]['.id']);
                $query->equal('rate-limit', $rateLimit);
                $this->client->query($query)->read();
            }

            $synced[] = $profileName;
        }

        return [
            'success' => true,
            'synced_profiles' => $synced,
        ];
    }

    /**
     * Format rate limit untuk Mikrotik
     * Format: rx/tx atau upload/download
     */
    protected function formatRateLimit(\App\Models\Package $package): string
    {
        $upload = $package->speed_upload . 'k';
        $download = $package->speed_download . 'k';

        // Format: upload/download
        return "{$upload}/{$download}";
    }

    protected function getProfileName(\App\Models\Package $package): string
    {
        return 'pkg-' . strtolower($package->code);
    }

    protected function getProfileForPackage(\App\Models\Package $package): string
    {
        return $this->getProfileName($package);
    }
}
```

### 2.3 Alur Isolir/Buka Akses

```
┌──────────────────────────────────────────────────────────────┐
│                    ALUR ISOLIR PELANGGAN                      │
└──────────────────────────────────────────────────────────────┘

    ┌─────────┐
    │  START  │
    └────┬────┘
         │
         ▼
┌────────────────────┐
│ Cek Invoice Jatuh  │
│ Tempo + Grace Days │
└─────────┬──────────┘
          │
          ▼
    ┌───────────┐     Tidak
    │  Overdue? ├──────────────┐
    └─────┬─────┘              │
          │ Ya                 │
          ▼                    ▼
┌────────────────────┐   ┌──────────┐
│ Queue: IsolateJob  │   │   END    │
└─────────┬──────────┘   └──────────┘
          │
          ▼
┌────────────────────┐
│ Koneksi ke Router  │
│ via Mikrotik API   │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ PPPoE: Ubah Profile│
│ ke "isolated"      │
│                    │
│ Static: Disable    │
│ ARP Entry          │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ Disconnect Active  │
│ Session (Kick)     │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ Update Status      │
│ Customer: isolated │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ Kirim Notifikasi   │
│ WhatsApp/SMS       │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ Catat ke           │
│ billing_logs       │
└─────────┬──────────┘
          │
          ▼
    ┌──────────┐
    │   END    │
    └──────────┘
```

---

## 3. Integrasi GenieACS (TR-069)

### 3.1 Konfigurasi GenieACS

```php
// config/genieacs.php
return [
    'nbi_url' => env('GENIEACS_NBI_URL', 'http://localhost:7557'),
    'timeout' => env('GENIEACS_TIMEOUT', 30),

    // Parameter TR-069 yang akan disync
    'sync_parameters' => [
        'InternetGatewayDevice.DeviceInfo.SerialNumber',
        'InternetGatewayDevice.DeviceInfo.Manufacturer',
        'InternetGatewayDevice.DeviceInfo.ModelName',
        'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
        'InternetGatewayDevice.ManagementServer.ConnectionRequestURL',
        'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username',
    ],
];
```

### 3.2 Service GenieACS

```php
// app/Services/GenieAcsService.php
namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDevice;
use Illuminate\Support\Facades\Http;

class GenieAcsService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('genieacs.nbi_url');
    }

    /**
     * Ambil semua device dari GenieACS
     */
    public function getDevices(array $filter = []): array
    {
        $query = [];

        if (!empty($filter)) {
            $query['query'] = json_encode($filter);
        }

        $response = Http::timeout(config('genieacs.timeout'))
            ->get("{$this->baseUrl}/devices", $query);

        return $response->json() ?? [];
    }

    /**
     * Cari device berdasarkan Serial Number
     */
    public function findDeviceBySerial(string $serialNumber): ?array
    {
        $filter = [
            'DeviceID.SerialNumber' => $serialNumber,
        ];

        $devices = $this->getDevices($filter);

        return $devices[0] ?? null;
    }

    /**
     * Ambil parameter device
     */
    public function getDeviceParameters(string $deviceId, array $parameters = []): array
    {
        if (empty($parameters)) {
            $parameters = config('genieacs.sync_parameters');
        }

        $device = $this->getDevice($deviceId);
        $result = [];

        foreach ($parameters as $param) {
            $value = $this->extractParameter($device, $param);
            $result[$param] = $value;
        }

        return $result;
    }

    /**
     * Ambil detail device
     */
    public function getDevice(string $deviceId): ?array
    {
        $response = Http::timeout(config('genieacs.timeout'))
            ->get("{$this->baseUrl}/devices/{$deviceId}");

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    /**
     * Set parameter device
     */
    public function setParameter(string $deviceId, string $parameter, $value): bool
    {
        $task = [
            'name' => 'setParameterValues',
            'parameterValues' => [
                [$parameter, $value, 'xsd:string'],
            ],
        ];

        $response = Http::timeout(config('genieacs.timeout'))
            ->post("{$this->baseUrl}/devices/{$deviceId}/tasks", $task);

        return $response->successful();
    }

    /**
     * Reboot device
     */
    public function rebootDevice(string $deviceId): bool
    {
        $task = [
            'name' => 'reboot',
        ];

        $response = Http::timeout(config('genieacs.timeout'))
            ->post("{$this->baseUrl}/devices/{$deviceId}/tasks", $task);

        return $response->successful();
    }

    /**
     * Factory reset device
     */
    public function factoryReset(string $deviceId): bool
    {
        $task = [
            'name' => 'factoryReset',
        ];

        $response = Http::timeout(config('genieacs.timeout'))
            ->post("{$this->baseUrl}/devices/{$deviceId}/tasks", $task);

        return $response->successful();
    }

    /**
     * Update firmware device
     */
    public function upgradeFirmware(string $deviceId, string $firmwareUrl): bool
    {
        $task = [
            'name' => 'download',
            'file' => $firmwareUrl,
        ];

        $response = Http::timeout(config('genieacs.timeout'))
            ->post("{$this->baseUrl}/devices/{$deviceId}/tasks", $task);

        return $response->successful();
    }

    /**
     * Ubah SSID WiFi pelanggan
     */
    public function setWifiSSID(string $deviceId, string $ssid, string $password): bool
    {
        // Set SSID
        $this->setParameter(
            $deviceId,
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
            $ssid
        );

        // Set Password
        $this->setParameter(
            $deviceId,
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey',
            $password
        );

        return true;
    }

    /**
     * Ubah kredensial PPPoE di ONT
     */
    public function setPPPoECredentials(string $deviceId, string $username, string $password): bool
    {
        $basePath = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1';

        $this->setParameter($deviceId, "{$basePath}.Username", $username);
        $this->setParameter($deviceId, "{$basePath}.Password", $password);

        return true;
    }

    /**
     * Sinkronisasi device pelanggan
     */
    public function syncCustomerDevice(Customer $customer): ?CustomerDevice
    {
        if (empty($customer->ont_serial)) {
            return null;
        }

        $genieDevice = $this->findDeviceBySerial($customer->ont_serial);

        if (!$genieDevice) {
            return null;
        }

        $deviceId = $genieDevice['_id'];
        $parameters = $this->getDeviceParameters($deviceId);

        $device = CustomerDevice::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'device_id' => $deviceId,
                'serial_number' => $customer->ont_serial,
                'manufacturer' => $parameters['InternetGatewayDevice.DeviceInfo.Manufacturer'] ?? null,
                'model' => $parameters['InternetGatewayDevice.DeviceInfo.ModelName'] ?? null,
                'firmware_version' => $parameters['InternetGatewayDevice.DeviceInfo.SoftwareVersion'] ?? null,
                'is_online' => isset($genieDevice['_lastInform']),
                'last_inform' => isset($genieDevice['_lastInform'])
                    ? \Carbon\Carbon::parse($genieDevice['_lastInform'])
                    : null,
                'parameters' => $parameters,
            ]
        );

        return $device;
    }

    /**
     * Extract parameter dari struktur GenieACS
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

        return $current['_value'] ?? $current;
    }
}
```

### 3.3 Alur Sinkronisasi GenieACS

```
┌──────────────────────────────────────────────────────────────┐
│                  ALUR SINKRONISASI GENIEACS                   │
└──────────────────────────────────────────────────────────────┘

    ┌─────────┐
    │  START  │
    └────┬────┘
         │
         ▼
┌────────────────────┐
│ Scheduler: Setiap  │
│ 15 menit           │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ Ambil daftar       │
│ Customer aktif     │
│ dengan ONT Serial  │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ Loop setiap        │
│ Customer           │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ Query GenieACS:    │
│ GET /devices       │
│ ?serial=XXX        │
└─────────┬──────────┘
          │
          ▼
    ┌───────────┐      Tidak
    │  Found?   ├───────────────┐
    └─────┬─────┘               │
          │ Ya                  │
          ▼                     ▼
┌────────────────────┐   ┌──────────────┐
│ Ambil Parameter:   │   │ Skip/Log     │
│ - Manufacturer     │   └──────────────┘
│ - Model            │
│ - Firmware         │
│ - Last Inform      │
│ - Online Status    │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ Update/Create      │
│ customer_devices   │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ Log ke             │
│ billing_logs       │
└─────────┬──────────┘
          │
          ▼
    ┌──────────┐
    │   END    │
    └──────────┘
```

---

## 4. Job Queue untuk Integrasi

### 4.1 Job Isolir Pelanggan

```php
// app/Jobs/IsolateCustomerJob.php
namespace App\Jobs;

use App\Models\Customer;
use App\Services\MikrotikService;
use App\Services\BillingLogService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IsolateCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 600]; // 1 menit, 5 menit, 10 menit

    protected Customer $customer;
    protected string $reason;

    public function __construct(Customer $customer, string $reason = 'Tunggakan pembayaran')
    {
        $this->customer = $customer;
        $this->reason = $reason;
    }

    public function handle(
        MikrotikService $mikrotik,
        BillingLogService $logger,
        NotificationService $notification
    ): void {
        try {
            // 1. Isolir di Mikrotik
            $result = $mikrotik->isolateCustomer($this->customer);

            // 2. Update status customer
            $this->customer->update([
                'status' => 'isolated',
                'isolation_reason' => $this->reason,
            ]);

            // 3. Log aktivitas
            $logger->log(
                'isolation_executed',
                $this->customer,
                'Pelanggan berhasil diisolir',
                [
                    'reason' => $this->reason,
                    'router_response' => $result,
                ]
            );

            // 4. Kirim notifikasi
            $notification->sendIsolationNotice($this->customer);

        } catch (\Exception $e) {
            $logger->log(
                'system_error',
                $this->customer,
                'Gagal isolir pelanggan: ' . $e->getMessage(),
                ['exception' => $e->getTraceAsString()],
                'failed'
            );

            throw $e;
        }
    }
}
```

### 4.2 Job Buka Akses

```php
// app/Jobs/OpenAccessJob.php
namespace App\Jobs;

use App\Models\Customer;
use App\Services\MikrotikService;
use App\Services\BillingLogService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OpenAccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    protected Customer $customer;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }

    public function handle(
        MikrotikService $mikrotik,
        BillingLogService $logger,
        NotificationService $notification
    ): void {
        try {
            // 1. Buka akses di Mikrotik
            $result = $mikrotik->openAccess($this->customer);

            // 2. Update status customer
            $this->customer->update([
                'status' => 'active',
                'isolation_reason' => null,
            ]);

            // 3. Log aktivitas
            $logger->log(
                'isolation_opened',
                $this->customer,
                'Akses pelanggan berhasil dibuka',
                ['router_response' => $result]
            );

            // 4. Kirim notifikasi
            $notification->sendAccessOpenedNotice($this->customer);

        } catch (\Exception $e) {
            $logger->log(
                'system_error',
                $this->customer,
                'Gagal buka akses: ' . $e->getMessage(),
                ['exception' => $e->getTraceAsString()],
                'failed'
            );

            throw $e;
        }
    }
}
```

---

## 5. Scheduler (Penjadwalan)

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Generate invoice setiap tanggal 1 jam 00:01
    $schedule->command('billing:generate-invoices')
        ->monthlyOn(1, '00:01')
        ->withoutOverlapping();

    // Cek dan isolir pelanggan overdue setiap jam 6 pagi
    $schedule->command('billing:check-overdue')
        ->dailyAt('06:00')
        ->withoutOverlapping();

    // Kirim reminder tagihan
    $schedule->command('billing:send-reminders')
        ->dailyAt('09:00')
        ->withoutOverlapping();

    // Sinkronisasi GenieACS setiap 15 menit
    $schedule->command('genieacs:sync-devices')
        ->everyFifteenMinutes()
        ->withoutOverlapping();

    // Sinkronisasi profile Mikrotik setiap hari jam 2 malam
    $schedule->command('mikrotik:sync-profiles')
        ->dailyAt('02:00')
        ->withoutOverlapping();
}
```

---

## 6. Event & Listener

```php
// Daftar Event yang tersedia:

// app/Events/InvoiceGenerated.php
// - Dipicu saat invoice baru dibuat
// - Listener: SendInvoiceNotification, AddToDebtHistory

// app/Events/PaymentReceived.php
// - Dipicu saat pembayaran diterima
// - Listener: UpdateInvoiceStatus, UpdateCustomerDebt, CheckAutoOpenAccess

// app/Events/CustomerIsolated.php
// - Dipicu saat pelanggan diisolir
// - Listener: SendIsolationNotification, LogIsolation

// app/Events/CustomerAccessOpened.php
// - Dipicu saat akses dibuka
// - Listener: SendAccessNotification, LogAccessOpened
```

---

## 7. Webhook Endpoints

```php
// routes/api.php

// Webhook dari Payment Gateway
Route::post('/webhook/payment/{provider}', [WebhookController::class, 'handlePayment']);

// Callback dari GenieACS (jika dibutuhkan)
Route::post('/webhook/genieacs/inform', [WebhookController::class, 'handleGenieInform']);
```
