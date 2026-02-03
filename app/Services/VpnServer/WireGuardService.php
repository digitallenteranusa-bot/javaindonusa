<?php

namespace App\Services\VpnServer;

use App\Models\Setting;
use App\Models\VpnServerClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;

class WireGuardService
{
    protected string $configPath = '/etc/wireguard/wg0.conf';

    // ================================================================
    // INSTALLATION CHECK
    // ================================================================

    public function isInstalled(): bool
    {
        return Process::run('which wg')->successful();
    }

    public function checkInstallation(): array
    {
        $installed = $this->isInstalled();
        $interfaceUp = false;
        $serverKeysExist = false;

        if ($installed) {
            $check = Process::run('ip link show wg0 2>/dev/null');
            $interfaceUp = $check->successful() && str_contains($check->output(), 'UP');
        }

        $privateKey = Setting::getValue('vpn_server', 'wg_private_key');
        $publicKey = Setting::getValue('vpn_server', 'wg_public_key');
        $serverKeysExist = !empty($privateKey) && !empty($publicKey);

        return [
            'wireguard_installed' => $installed,
            'interface_up' => $interfaceUp,
            'server_keys_exist' => $serverKeysExist,
            'all_ready' => $installed && $serverKeysExist,
        ];
    }

    // ================================================================
    // KEY MANAGEMENT
    // ================================================================

    public function generateKeyPair(): array
    {
        $privateKey = trim(Process::run('wg genkey')->output());
        $publicKey = trim(Process::input($privateKey)->run('wg pubkey')->output());

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    public function generatePresharedKey(): string
    {
        return trim(Process::run('wg genpsk')->output());
    }

    public function ensureServerKeys(): array
    {
        $privateKey = Setting::getValue('vpn_server', 'wg_private_key');
        $publicKey = Setting::getValue('vpn_server', 'wg_public_key');

        if (empty($privateKey) || empty($publicKey)) {
            $keys = $this->generateKeyPair();
            Setting::setValue('vpn_server', 'wg_private_key', $keys['private_key'], 'string');
            Setting::setValue('vpn_server', 'wg_public_key', $keys['public_key'], 'string');

            Log::info('WireGuard server keys generated');
            return $keys;
        }

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    public function getServerPublicKey(): ?string
    {
        return Setting::getValue('vpn_server', 'wg_public_key');
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
        $privateKey = $s['wg_private_key'] ?? '';
        $serverAddress = $s['server_address'] ?? '10.200.1.0/24';
        $port = $s['wg_port'] ?? 51820;

        // Get server IP (first usable IP in subnet)
        $parts = explode('/', $serverAddress);
        $serverIp = preg_replace('/\.\d+$/', '.1', $parts[0]);
        $mask = $parts[1] ?? '24';

        $config = "[Interface]\n";
        $config .= "PrivateKey = {$privateKey}\n";
        $config .= "Address = {$serverIp}/{$mask}\n";
        $config .= "ListenPort = {$port}\n";
        $config .= "SaveConfig = false\n";
        $config .= "PostUp = iptables -A FORWARD -i wg0 -j ACCEPT; iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE\n";
        $config .= "PostDown = iptables -D FORWARD -i wg0 -j ACCEPT; iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE\n";
        $config .= "\n";

        // Add peers
        $peers = VpnServerClient::wireGuard()->enabled()->get();

        foreach ($peers as $peer) {
            $config .= "# Peer: {$peer->name}\n";
            $config .= "[Peer]\n";
            $config .= "PublicKey = {$peer->public_key}\n";

            if ($peer->preshared_key) {
                $config .= "PresharedKey = {$peer->preshared_key}\n";
            }

            // AllowedIPs: client's VPN IP + optional LAN subnet
            $allowedIps = $peer->client_vpn_ip . '/32';
            if ($peer->mikrotik_lan_subnet) {
                $allowedIps .= ', ' . $peer->mikrotik_lan_subnet;
            }

            $config .= "AllowedIPs = {$allowedIps}\n";
            $config .= "\n";
        }

        return $config;
    }

    public function writeServerConfig(): bool
    {
        try {
            $config = $this->generateServerConfig();
            $result = Process::input($config)->run('sudo tee ' . $this->configPath);

            if ($result->successful()) {
                Process::run('sudo chmod 600 ' . $this->configPath);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to write WireGuard config', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function generateMikrotikScript(VpnServerClient $client): string
    {
        $s = Setting::vpnServer();
        $serverPublicKey = $s['wg_public_key'] ?? '';
        $endpoint = $s['public_endpoint'] ?? '';
        $port = $s['wg_port'] ?? 51820;
        $serverAddress = $s['server_address'] ?? '10.200.1.0/24';

        // Calculate server IP
        $serverIp = preg_replace('/\.\d+$/', '.1', explode('/', $serverAddress)[0]);

        return <<<EOT
# ============================================================
# WireGuard Client for Mikrotik v7+ - {$client->name}
# VPN IP: {$client->client_vpn_ip}
# Generated: {$client->last_generated_at}
# ============================================================

# IMPORTANT: Requires RouterOS v7 or later!

# STEP 1: Create WireGuard interface
/interface wireguard add name=wg-billing \\
    listen-port=13231 \\
    mtu=1420 \\
    private-key="{$client->private_key}"

# STEP 2: Assign IP address
/ip address add address={$client->client_vpn_ip}/24 interface=wg-billing

# STEP 3: Add VPN Server as peer
/interface wireguard peers add interface=wg-billing \\
    public-key="{$serverPublicKey}" \\
    endpoint-address={$endpoint} \\
    endpoint-port={$port} \\
    allowed-address=10.200.1.0/24 \\
    persistent-keepalive=25

# STEP 4: Add firewall rules
/ip firewall filter add chain=input src-address=10.200.1.0/24 action=accept \\
    comment="Allow WireGuard VPN" place-before=0

# STEP 5: Add route (optional - for accessing server network)
/ip route add dst-address={$serverAddress} gateway=wg-billing

# STEP 6: Enable interface
/interface wireguard enable wg-billing

# ============================================================
# Verification Commands:
# ============================================================
# /interface wireguard print
# /interface wireguard peers print
# /ping {$serverIp}
#
# Check handshake status:
# /interface wireguard peers print stats
EOT;
    }

    // ================================================================
    // SERVICE MANAGEMENT
    // ================================================================

    public function startInterface(): array
    {
        try {
            $this->writeServerConfig();

            $result = Process::run('sudo wg-quick up wg0');

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'WireGuard interface started' : $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to start WireGuard', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function stopInterface(): array
    {
        try {
            $result = Process::run('sudo wg-quick down wg0');

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'WireGuard interface stopped' : $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to stop WireGuard', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function restartInterface(): array
    {
        Process::run('sudo wg-quick down wg0 2>/dev/null');
        return $this->startInterface();
    }

    public function syncConfig(): array
    {
        try {
            $this->writeServerConfig();

            // Try to sync without restart
            $result = Process::run('sudo bash -c "wg syncconf wg0 <(wg-quick strip wg0)"');

            if (!$result->successful()) {
                // Fallback to restart
                return $this->restartInterface();
            }

            return ['success' => true, 'message' => 'WireGuard config synced'];
        } catch (\Exception $e) {
            Log::error('Failed to sync WireGuard config', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getInterfaceStatus(): array
    {
        try {
            $check = Process::run('ip link show wg0 2>/dev/null');
            $isUp = $check->successful() && str_contains($check->output(), 'UP');

            $wgShow = Process::run('sudo wg show wg0 2>/dev/null');

            return [
                'running' => $isUp,
                'details' => $wgShow->output(),
            ];
        } catch (\Exception $e) {
            return [
                'running' => false,
                'details' => $e->getMessage(),
            ];
        }
    }

    // ================================================================
    // STATUS MONITORING
    // ================================================================

    public function getPeerStatus(): array
    {
        $result = Process::run('sudo wg show wg0 dump 2>/dev/null');

        if (!$result->successful()) {
            return [];
        }

        $peers = [];
        $lines = explode("\n", trim($result->output()));

        foreach ($lines as $i => $line) {
            // Skip header line (interface info)
            if ($i === 0 || empty($line)) {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) >= 8) {
                $peers[$parts[0]] = [
                    'public_key' => $parts[0],
                    'preshared_key' => $parts[1] !== '(none)' ? $parts[1] : null,
                    'endpoint' => $parts[2] !== '(none)' ? $parts[2] : null,
                    'allowed_ips' => $parts[3],
                    'last_handshake' => $parts[4] !== '0' ? (int) $parts[4] : null,
                    'rx_bytes' => (int) $parts[5],
                    'tx_bytes' => (int) $parts[6],
                    'persistent_keepalive' => $parts[7] !== 'off' ? (int) $parts[7] : null,
                ];
            }
        }

        return $peers;
    }

    public function updateClientStatuses(): void
    {
        $liveStatus = $this->getPeerStatus();

        $clients = VpnServerClient::wireGuard()->get();

        foreach ($clients as $client) {
            if (isset($liveStatus[$client->public_key])) {
                $info = $liveStatus[$client->public_key];

                // Consider connected if handshake within last 3 minutes
                $isConnected = $info['last_handshake'] &&
                    ($info['last_handshake'] > (time() - 180));

                $client->update([
                    'connected_at' => $isConnected ? now() : $client->connected_at,
                    'disconnected_at' => $isConnected ? null : ($client->connected_at ? now() : null),
                    'remote_ip' => $info['endpoint'] ? explode(':', $info['endpoint'])[0] : null,
                    'bytes_received' => $info['rx_bytes'],
                    'bytes_sent' => $info['tx_bytes'],
                ]);
            }
        }
    }

    public function getConnectedClients(): array
    {
        $liveStatus = $this->getPeerStatus();
        $connected = [];

        $clients = VpnServerClient::wireGuard()->get()->keyBy('public_key');

        foreach ($liveStatus as $publicKey => $info) {
            if ($info['last_handshake'] && ($info['last_handshake'] > (time() - 180))) {
                $client = $clients->get($publicKey);

                $connected[] = [
                    'name' => $client?->name ?? 'Unknown',
                    'public_key' => $publicKey,
                    'endpoint' => $info['endpoint'],
                    'last_handshake' => $info['last_handshake'],
                    'bytes_received' => $info['rx_bytes'],
                    'bytes_sent' => $info['tx_bytes'],
                ];
            }
        }

        return $connected;
    }
}
