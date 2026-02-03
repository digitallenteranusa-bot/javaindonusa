<?php

namespace App\Services\VpnServer;

use App\Models\Setting;
use App\Models\VpnServerClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;

class OpenVpnService
{
    protected string $pkiPath = '/etc/openvpn/easy-rsa/pki';
    protected string $serverPath = '/etc/openvpn/server';

    // ================================================================
    // INSTALLATION CHECK
    // ================================================================

    public function isInstalled(): bool
    {
        return Process::run('which openvpn')->successful();
    }

    public function checkInstallation(): array
    {
        $openvpnInstalled = $this->isInstalled();
        $easyrsaInstalled = File::exists('/usr/share/easy-rsa/easyrsa');
        $pkiInitialized = File::exists($this->pkiPath . '/ca.crt');
        $serverCertExists = File::exists($this->serverPath . '/server.crt');
        $dhExists = File::exists($this->serverPath . '/dh.pem');
        $taKeyExists = File::exists($this->serverPath . '/ta.key');

        $serviceRunning = false;
        if ($openvpnInstalled) {
            $status = Process::run('systemctl is-active openvpn-server@server');
            $serviceRunning = trim($status->output()) === 'active';
        }

        return [
            'openvpn_installed' => $openvpnInstalled,
            'easyrsa_installed' => $easyrsaInstalled,
            'pki_initialized' => $pkiInitialized,
            'server_cert_exists' => $serverCertExists,
            'dh_exists' => $dhExists,
            'ta_key_exists' => $taKeyExists,
            'service_running' => $serviceRunning,
            'all_ready' => $openvpnInstalled && $easyrsaInstalled && $pkiInitialized &&
                          $serverCertExists && $dhExists && $taKeyExists,
        ];
    }

    // ================================================================
    // PKI INITIALIZATION
    // ================================================================

    public function initializePki(): array
    {
        try {
            Process::run('sudo mkdir -p /etc/openvpn/easy-rsa');
            Process::run('sudo mkdir -p ' . $this->serverPath . '/ccd');
            Process::run('sudo cp -r /usr/share/easy-rsa/* /etc/openvpn/easy-rsa/');

            $result = Process::path('/etc/openvpn/easy-rsa')->run('sudo ./easyrsa init-pki');

            Log::info('OpenVPN PKI initialized', ['output' => $result->output()]);

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'PKI initialized successfully' : $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to initialize OpenVPN PKI', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function generateCaCertificate(): array
    {
        try {
            $result = Process::path('/etc/openvpn/easy-rsa')
                ->env(['EASYRSA_BATCH' => '1'])
                ->run('sudo ./easyrsa build-ca nopass');

            if ($result->successful()) {
                Process::run('sudo cp ' . $this->pkiPath . '/ca.crt ' . $this->serverPath . '/');

                // Store CA cert in settings
                $caCert = File::get($this->pkiPath . '/ca.crt');
                Setting::setValue('vpn_server', 'ca_cert', $caCert, 'string');

                Log::info('OpenVPN CA certificate generated');
            }

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'CA certificate generated' : $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate CA certificate', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function generateServerCertificate(): array
    {
        try {
            $result = Process::path('/etc/openvpn/easy-rsa')
                ->env(['EASYRSA_BATCH' => '1'])
                ->run('sudo ./easyrsa build-server-full server nopass');

            if ($result->successful()) {
                Process::run('sudo cp ' . $this->pkiPath . '/issued/server.crt ' . $this->serverPath . '/');
                Process::run('sudo cp ' . $this->pkiPath . '/private/server.key ' . $this->serverPath . '/');

                Log::info('OpenVPN server certificate generated');
            }

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'Server certificate generated' : $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate server certificate', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function generateDhParams(): array
    {
        try {
            $result = Process::path('/etc/openvpn/easy-rsa')
                ->timeout(600)
                ->run('sudo ./easyrsa gen-dh');

            if ($result->successful()) {
                Process::run('sudo cp ' . $this->pkiPath . '/dh.pem ' . $this->serverPath . '/');
                Log::info('OpenVPN DH parameters generated');
            }

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'DH parameters generated' : $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate DH parameters', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function generateTaKey(): array
    {
        try {
            $result = Process::run('sudo openvpn --genkey secret ' . $this->serverPath . '/ta.key');

            if ($result->successful()) {
                $taKey = File::get($this->serverPath . '/ta.key');
                Setting::setValue('vpn_server', 'ta_key', $taKey, 'string');
                Log::info('OpenVPN TA key generated');
            }

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'TA key generated' : $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate TA key', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ================================================================
    // CLIENT CERTIFICATE MANAGEMENT
    // ================================================================

    public function generateClientCertificate(string $commonName): array
    {
        try {
            $result = Process::path('/etc/openvpn/easy-rsa')
                ->env(['EASYRSA_BATCH' => '1'])
                ->run("sudo ./easyrsa build-client-full {$commonName} nopass");

            if ($result->successful()) {
                Log::info('OpenVPN client certificate generated', ['common_name' => $commonName]);

                return [
                    'success' => true,
                    'cert' => File::get($this->pkiPath . '/issued/' . $commonName . '.crt'),
                    'key' => File::get($this->pkiPath . '/private/' . $commonName . '.key'),
                ];
            }

            return ['success' => false, 'message' => $result->errorOutput()];
        } catch (\Exception $e) {
            Log::error('Failed to generate client certificate', [
                'common_name' => $commonName,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function revokeClientCertificate(string $commonName): array
    {
        try {
            $result = Process::path('/etc/openvpn/easy-rsa')
                ->env(['EASYRSA_BATCH' => '1'])
                ->run("sudo ./easyrsa revoke {$commonName}");

            if ($result->successful()) {
                // Generate CRL
                Process::path('/etc/openvpn/easy-rsa')
                    ->env(['EASYRSA_BATCH' => '1'])
                    ->run('sudo ./easyrsa gen-crl');

                Process::run('sudo cp ' . $this->pkiPath . '/crl.pem ' . $this->serverPath . '/');

                Log::info('OpenVPN client certificate revoked', ['common_name' => $commonName]);
            }

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'Certificate revoked' : $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to revoke client certificate', [
                'common_name' => $commonName,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ================================================================
    // IP ADDRESS MANAGEMENT
    // ================================================================

    public function getNextAvailableClientIp(): string
    {
        $serverAddress = Setting::getValue('vpn_server', 'server_address', '10.200.1.0/24');
        $parts = explode('.', explode('/', $serverAddress)[0]);
        $baseIp = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.';

        $usedIps = VpnServerClient::withTrashed()
            ->pluck('client_vpn_ip')
            ->map(fn($ip) => (int) explode('.', $ip)[3])
            ->toArray();

        // Reserve .1 for server
        $usedIps[] = 1;

        for ($i = 2; $i <= 254; $i++) {
            if (!in_array($i, $usedIps)) {
                return $baseIp . $i;
            }
        }

        throw new \Exception('No available IP addresses in VPN subnet');
    }

    // ================================================================
    // CONFIGURATION GENERATION
    // ================================================================

    public function generateServerConfig(): string
    {
        $s = Setting::vpnServer();
        $port = $s['port'] ?? 1194;
        $protocol = $s['protocol'] ?? 'udp';
        $serverAddress = $s['server_address'] ?? '10.200.1.0/24';
        $network = explode('/', $serverAddress)[0];

        $config = <<<EOT
port {$port}
proto {$protocol}
dev tun
topology subnet

ca /etc/openvpn/server/ca.crt
cert /etc/openvpn/server/server.crt
key /etc/openvpn/server/server.key
dh /etc/openvpn/server/dh.pem
tls-auth /etc/openvpn/server/ta.key 0

server {$network} 255.255.255.0
client-config-dir /etc/openvpn/server/ccd

cipher AES-256-GCM
auth SHA256
data-ciphers AES-256-GCM:AES-128-GCM:CHACHA20-POLY1305

keepalive 10 120
persist-key
persist-tun

status /var/log/openvpn/openvpn-status.log
log-append /var/log/openvpn/openvpn.log
verb 3

user nobody
group nogroup

EOT;

        // Add routes for each client's LAN subnet
        $clients = VpnServerClient::openVpn()->enabled()->whereNotNull('mikrotik_lan_subnet')->get();
        foreach ($clients as $client) {
            $config .= "# Route for {$client->name}\n";
            $config .= "route {$client->mikrotik_lan_subnet}\n";
        }

        return $config;
    }

    public function generateClientCcd(VpnServerClient $client): string
    {
        $ccd = "ifconfig-push {$client->client_vpn_ip} 255.255.255.0\n";

        if ($client->mikrotik_lan_subnet) {
            $ccd .= "iroute {$client->mikrotik_lan_subnet}\n";
        }

        return $ccd;
    }

    public function writeClientCcd(VpnServerClient $client): bool
    {
        try {
            $ccd = $this->generateClientCcd($client);
            $ccdPath = $this->serverPath . '/ccd/' . $client->common_name;

            Process::input($ccd)->run('sudo tee ' . $ccdPath);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to write client CCD', [
                'client' => $client->name,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function generateClientConfig(VpnServerClient $client): string
    {
        $s = Setting::vpnServer();
        $endpoint = $s['public_endpoint'] ?? '';
        $port = $s['port'] ?? 1194;
        $protocol = $s['protocol'] ?? 'udp';
        $caCert = $s['ca_cert'] ?? '';
        $taKey = $s['ta_key'] ?? '';

        $clientCert = '';
        $clientKey = '';

        if (File::exists($this->pkiPath . '/issued/' . $client->common_name . '.crt')) {
            $clientCert = File::get($this->pkiPath . '/issued/' . $client->common_name . '.crt');
        }

        if (File::exists($this->pkiPath . '/private/' . $client->common_name . '.key')) {
            $clientKey = File::get($this->pkiPath . '/private/' . $client->common_name . '.key');
        }

        return <<<EOT
client
dev tun
proto {$protocol}
remote {$endpoint} {$port}
resolv-retry infinite
nobind
persist-key
persist-tun
remote-cert-tls server
cipher AES-256-GCM
auth SHA256
key-direction 1
verb 3

<ca>
{$caCert}
</ca>

<cert>
{$clientCert}
</cert>

<key>
{$clientKey}
</key>

<tls-auth>
{$taKey}
</tls-auth>
EOT;
    }

    public function generateMikrotikScript(VpnServerClient $client): string
    {
        $s = Setting::vpnServer();
        $endpoint = $s['public_endpoint'] ?? '';
        $port = $s['port'] ?? 1194;

        return <<<EOT
# ============================================================
# OpenVPN Client for Mikrotik - {$client->name}
# VPN IP: {$client->client_vpn_ip}
# Generated: {$client->last_generated_at}
# ============================================================

# STEP 1: Upload certificates to Mikrotik
# Upload these files via WinBox or FTP:
# - ca.crt
# - {$client->common_name}.crt
# - {$client->common_name}.key

# STEP 2: Import certificates
/certificate import file-name=ca.crt passphrase=""
/certificate import file-name={$client->common_name}.crt passphrase=""
/certificate import file-name={$client->common_name}.key passphrase=""

# STEP 3: Create OVPN client interface
/interface ovpn-client add name=ovpn-billing \\
    connect-to={$endpoint} port={$port} \\
    mode=ip protocol=udp \\
    user={$client->common_name} \\
    certificate={$client->common_name}.crt_0 \\
    cipher=aes256-cbc auth=sha256 \\
    add-default-route=no \\
    comment="VPN to Billing Server"

# STEP 4: Add firewall rule to allow VPN traffic
/ip firewall filter add chain=input src-address=10.200.1.0/24 action=accept \\
    comment="Allow VPN Billing" place-before=0

# STEP 5: Enable interface
/interface ovpn-client enable ovpn-billing

# ============================================================
# Verification Commands:
# ============================================================
# /interface ovpn-client print
# /interface ovpn-client monitor ovpn-billing
# /ping 10.200.1.1
EOT;
    }

    // ================================================================
    // SERVICE MANAGEMENT
    // ================================================================

    public function writeServerConfig(): bool
    {
        try {
            Process::run('sudo mkdir -p /var/log/openvpn');

            $config = $this->generateServerConfig();
            Process::input($config)->run('sudo tee /etc/openvpn/server/server.conf');

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to write server config', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function startService(): array
    {
        try {
            $this->writeServerConfig();

            $result = Process::run('sudo systemctl start openvpn-server@server');
            Process::run('sudo systemctl enable openvpn-server@server');

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'OpenVPN service started' : $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to start OpenVPN service', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function stopService(): array
    {
        try {
            $result = Process::run('sudo systemctl stop openvpn-server@server');

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'OpenVPN service stopped' : $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to stop OpenVPN service', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function restartService(): array
    {
        try {
            $this->writeServerConfig();

            $result = Process::run('sudo systemctl restart openvpn-server@server');

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'OpenVPN service restarted' : $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to restart OpenVPN service', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getServiceStatus(): array
    {
        try {
            $status = Process::run('systemctl is-active openvpn-server@server');
            $isActive = trim($status->output()) === 'active';

            $statusInfo = Process::run('systemctl status openvpn-server@server --no-pager -l');

            return [
                'running' => $isActive,
                'status' => trim($status->output()),
                'details' => $statusInfo->output(),
            ];
        } catch (\Exception $e) {
            return [
                'running' => false,
                'status' => 'unknown',
                'details' => $e->getMessage(),
            ];
        }
    }

    // ================================================================
    // STATUS MONITORING
    // ================================================================

    public function getConnectedClients(): array
    {
        $statusFile = '/var/log/openvpn/openvpn-status.log';

        if (!File::exists($statusFile)) {
            return [];
        }

        try {
            $content = File::get($statusFile);
            $lines = explode("\n", $content);

            $clients = [];
            $inClientList = false;

            foreach ($lines as $line) {
                if (str_starts_with($line, 'CLIENT_LIST')) {
                    $parts = explode(',', $line);
                    if (count($parts) >= 5) {
                        $clients[] = [
                            'common_name' => $parts[1],
                            'real_address' => $parts[2],
                            'virtual_address' => $parts[3],
                            'bytes_received' => (int) $parts[5],
                            'bytes_sent' => (int) $parts[6],
                            'connected_since' => $parts[7] ?? null,
                        ];
                    }
                }
            }

            return $clients;
        } catch (\Exception $e) {
            Log::warning('Failed to parse OpenVPN status', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function updateClientStatuses(): void
    {
        $connectedClients = $this->getConnectedClients();

        // Build lookup by common name
        $connected = collect($connectedClients)->keyBy('common_name');

        // Update all OpenVPN clients
        $clients = VpnServerClient::openVpn()->get();

        foreach ($clients as $client) {
            if ($connected->has($client->common_name)) {
                $info = $connected->get($client->common_name);
                $client->update([
                    'connected_at' => now(),
                    'disconnected_at' => null,
                    'remote_ip' => explode(':', $info['real_address'])[0] ?? null,
                    'bytes_received' => $info['bytes_received'],
                    'bytes_sent' => $info['bytes_sent'],
                ]);
            } else {
                // Mark as disconnected if was connected
                if ($client->connected_at && !$client->disconnected_at) {
                    $client->update(['disconnected_at' => now()]);
                }
            }
        }
    }
}
