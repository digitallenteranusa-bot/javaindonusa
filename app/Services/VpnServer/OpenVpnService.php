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

        // Check if PKI directory is initialized (has openssl-easyrsa.cnf)
        // Use sudo cat with /dev/null redirection - cat is already in sudoers
        $pkiDirExists = Process::run('sudo cat ' . $this->pkiPath . '/openssl-easyrsa.cnf > /dev/null 2>&1')->successful();

        // Check if CA certificate exists
        $caCertExists = Process::run('sudo cat ' . $this->pkiPath . '/ca.crt > /dev/null 2>&1')->successful();

        // Check server files
        $serverCertExists = Process::run('sudo cat ' . $this->serverPath . '/server.crt > /dev/null 2>&1')->successful();
        $dhExists = Process::run('sudo cat ' . $this->serverPath . '/dh.pem > /dev/null 2>&1')->successful();
        $taKeyExists = Process::run('sudo cat ' . $this->serverPath . '/ta.key > /dev/null 2>&1')->successful();

        $serviceRunning = false;
        if ($openvpnInstalled) {
            $status = Process::run('systemctl is-active openvpn-server@server');
            $serviceRunning = trim($status->output()) === 'active';
        }

        return [
            'openvpn_installed' => $openvpnInstalled,
            'easyrsa_installed' => $easyrsaInstalled,
            'pki_initialized' => $pkiDirExists,      // PKI directory exists
            'ca_cert_exists' => $caCertExists,        // CA certificate exists
            'server_cert_exists' => $serverCertExists,
            'dh_exists' => $dhExists,
            'ta_key_exists' => $taKeyExists,
            'service_running' => $serviceRunning,
            'all_ready' => $openvpnInstalled && $easyrsaInstalled && $caCertExists &&
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

            if ($result->successful()) {
                // Create vars file to enable batch mode (no interactive prompts)
                $varsContent = <<<'VARS'
# Easy-RSA vars file for non-interactive mode
set_var EASYRSA_BATCH "yes"
set_var EASYRSA_REQ_CN "Easy-RSA CA"
set_var EASYRSA_REQ_COUNTRY "ID"
set_var EASYRSA_REQ_PROVINCE "East Java"
set_var EASYRSA_REQ_CITY "Surabaya"
set_var EASYRSA_REQ_ORG "Java Indonusa"
set_var EASYRSA_REQ_EMAIL "admin@javaindonusa.net"
set_var EASYRSA_REQ_OU "IT"
set_var EASYRSA_KEY_SIZE 2048
set_var EASYRSA_CA_EXPIRE 3650
set_var EASYRSA_CERT_EXPIRE 825
VARS;
                Process::run('echo ' . escapeshellarg($varsContent) . ' | sudo tee /etc/openvpn/easy-rsa/pki/vars > /dev/null');
            }

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
            // EASYRSA_BATCH is set in pki/vars file created during init
            $result = Process::path('/etc/openvpn/easy-rsa')
                ->run('sudo ./easyrsa build-ca nopass');

            if ($result->successful()) {
                Process::run('sudo cp ' . $this->pkiPath . '/ca.crt ' . $this->serverPath . '/');

                // Store CA cert in settings (use sudo cat since files are owned by root)
                $catResult = Process::run('sudo cat ' . $this->pkiPath . '/ca.crt');
                if ($catResult->successful()) {
                    Setting::setValue('vpn_server', 'ca_cert', $catResult->output(), 'string');
                }

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
            // EASYRSA_BATCH is set in pki/vars file created during init
            $result = Process::path('/etc/openvpn/easy-rsa')
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
                // Change ownership so www-data can read, or use sudo cat
                Process::run('sudo chmod 644 ' . $this->serverPath . '/ta.key');

                // Read file using sudo cat since it's owned by root
                $catResult = Process::run('sudo cat ' . $this->serverPath . '/ta.key');
                if ($catResult->successful()) {
                    $taKey = $catResult->output();
                    Setting::setValue('vpn_server', 'ta_key', $taKey, 'string');
                    Log::info('OpenVPN TA key generated');
                }
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
            // Clean up any existing certificate files for this common name
            $this->cleanupClientCertificateFiles($commonName);

            // EASYRSA_BATCH is set in pki/vars file created during init
            $result = Process::path('/etc/openvpn/easy-rsa')
                ->run("sudo ./easyrsa build-client-full {$commonName} nopass");

            if ($result->successful()) {
                Log::info('OpenVPN client certificate generated', ['common_name' => $commonName]);

                // Read files using sudo cat since they're owned by root
                $certResult = Process::run('sudo cat ' . $this->pkiPath . '/issued/' . $commonName . '.crt');
                $keyResult = Process::run('sudo cat ' . $this->pkiPath . '/private/' . $commonName . '.key');

                return [
                    'success' => true,
                    'cert' => $certResult->output(),
                    'key' => $keyResult->output(),
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

    /**
     * Clean up existing certificate files for a client (internal use)
     */
    protected function cleanupClientCertificateFiles(string $commonName): void
    {
        $this->cleanupClientFiles($commonName);
    }

    /**
     * Clean up all certificate files for a client (public method)
     */
    public function cleanupClientFiles(string $commonName): void
    {
        // Remove request file
        Process::run("sudo rm -f {$this->pkiPath}/reqs/{$commonName}.req");

        // Remove issued certificate
        Process::run("sudo rm -f {$this->pkiPath}/issued/{$commonName}.crt");

        // Remove private key
        Process::run("sudo rm -f {$this->pkiPath}/private/{$commonName}.key");

        // Remove CCD file
        Process::run("sudo rm -f {$this->serverPath}/ccd/{$commonName}");

        Log::info('Cleaned up certificate files', ['common_name' => $commonName]);
    }

    public function revokeClientCertificate(string $commonName): array
    {
        try {
            // EASYRSA_BATCH is set in pki/vars file created during init
            $result = Process::path('/etc/openvpn/easy-rsa')
                ->run("sudo ./easyrsa revoke {$commonName}");

            if ($result->successful()) {
                // Generate CRL
                Process::path('/etc/openvpn/easy-rsa')
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
        // OpenVPN uses separate subnet (10.200.2.0/24) to avoid conflict with WireGuard
        $serverAddress = Setting::getValue('vpn_server', 'openvpn_server_address', '10.200.2.0/24');
        $parts = explode('.', explode('/', $serverAddress)[0]);
        $baseIp = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.';

        // Only get IPs from OpenVPN clients to avoid conflict with WireGuard subnet
        $usedIps = VpnServerClient::where('protocol', VpnServerClient::PROTOCOL_OPENVPN)
            ->pluck('client_vpn_ip')
            ->filter(fn($ip) => str_starts_with($ip, $baseIp)) // Only same subnet
            ->map(fn($ip) => (int) explode('.', $ip)[3])
            ->toArray();

        // Reserve .1 for server
        $usedIps[] = 1;

        for ($i = 2; $i <= 254; $i++) {
            if (!in_array($i, $usedIps)) {
                return $baseIp . $i;
            }
        }

        throw new \Exception('No available IP addresses in OpenVPN subnet');
    }

    // ================================================================
    // CONFIGURATION GENERATION
    // ================================================================

    public function generateServerConfig(): string
    {
        $s = Setting::vpnServer();
        $port = $s['port'] ?? 1194;
        $protocol = $s['protocol'] ?? 'udp';
        // OpenVPN uses separate subnet (10.200.2.0/24) to avoid conflict with WireGuard
        $serverAddress = $s['openvpn_server_address'] ?? '10.200.2.0/24';
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

        // Use sudo cat to read files owned by root
        $certResult = Process::run('sudo cat ' . $this->pkiPath . '/issued/' . $client->common_name . '.crt 2>/dev/null');
        if ($certResult->successful()) {
            $clientCert = $certResult->output();
        }

        $keyResult = Process::run('sudo cat ' . $this->pkiPath . '/private/' . $client->common_name . '.key 2>/dev/null');
        if ($keyResult->successful()) {
            $clientKey = $keyResult->output();
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
        $serverAddress = $s['openvpn_server_address'] ?? '10.200.2.0/24';
        $networkParts = explode('.', explode('/', $serverAddress)[0]);
        $serverIp = $networkParts[0] . '.' . $networkParts[1] . '.' . $networkParts[2] . '.1';

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
/ip firewall filter add chain=input src-address={$serverAddress} action=accept \\
    comment="Allow VPN Billing" place-before=0

# STEP 5: Enable interface
/interface ovpn-client enable ovpn-billing

# ============================================================
# Verification Commands:
# ============================================================
# /interface ovpn-client print
# /interface ovpn-client monitor ovpn-billing
# /ping {$serverIp}
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

        try {
            // Use sudo cat to read log file owned by root
            $result = Process::run('sudo cat ' . $statusFile . ' 2>/dev/null');
            if (!$result->successful()) {
                return [];
            }

            $content = $result->output();
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
