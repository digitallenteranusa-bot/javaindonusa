<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Models\VpnConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VpnController extends Controller
{
    /**
     * Display VPN configuration page for a router
     */
    public function index(Router $router)
    {
        $router->load('vpnConfigs');

        $configs = [];
        foreach (VpnConfig::getProtocols() as $protocol => $label) {
            $existing = $router->vpnConfigs->firstWhere('protocol', $protocol);

            if ($existing) {
                $configs[$protocol] = $existing->toArray();
                $configs[$protocol]['settings'] = $existing->mergeWithDefaults();
            } else {
                $temp = new VpnConfig(['protocol' => $protocol]);
                $configs[$protocol] = [
                    'id' => null,
                    'protocol' => $protocol,
                    'enabled' => false,
                    'settings' => $temp->getDefaultSettings(),
                    'generated_script' => null,
                    'last_generated_at' => null,
                ];
            }
        }

        return Inertia::render('Admin/Router/VpnConfig', [
            'router' => $router,
            'configs' => $configs,
            'protocols' => VpnConfig::getProtocols(),
            'protocolDescriptions' => VpnConfig::getProtocolDescriptions(),
            'isRouterV7' => $this->isRouterV7($router),
        ]);
    }

    /**
     * Generate VPN script for a specific protocol
     */
    public function generate(Request $request, Router $router, string $protocol)
    {
        if (!array_key_exists($protocol, VpnConfig::getProtocols())) {
            return back()->with('error', 'Protocol tidak valid');
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'enabled' => 'boolean',
        ]);

        // Get or create config
        $config = VpnConfig::updateOrCreate(
            ['router_id' => $router->id, 'protocol' => $protocol],
            [
                'enabled' => $validated['enabled'] ?? false,
                'settings' => $validated['settings'],
            ]
        );

        // Generate script
        $script = $this->generateScript($router, $config);

        $config->update([
            'generated_script' => $script,
            'last_generated_at' => now(),
        ]);

        return back()->with('success', 'Script VPN berhasil digenerate');
    }

    /**
     * Download generated script
     */
    public function download(Router $router, string $protocol)
    {
        $config = VpnConfig::where('router_id', $router->id)
            ->where('protocol', $protocol)
            ->firstOrFail();

        if (!$config->generated_script) {
            return back()->with('error', 'Script belum digenerate');
        }

        $filename = "vpn-{$protocol}-{$router->name}.rsc";

        return response($config->generated_script)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Generate RouterOS script based on protocol and settings
     */
    protected function generateScript(Router $router, VpnConfig $config): string
    {
        $isV7 = $this->isRouterV7($router);
        $settings = $config->mergeWithDefaults();
        $templateFile = $this->getTemplateFile($config->protocol, $isV7);

        if (Storage::disk('local')->exists($templateFile)) {
            $template = Storage::disk('local')->get($templateFile);
            return $this->processTemplate($template, $settings, $router);
        }

        // Fallback to inline generation
        return match ($config->protocol) {
            'l2tp' => $this->generateL2tpScript($settings, $router, $isV7),
            'pptp' => $this->generatePptpScript($settings, $router, $isV7),
            'sstp' => $this->generateSstpScript($settings, $router, $isV7),
            'wireguard' => $this->generateWireguardScript($settings, $router),
            default => '# Unknown protocol',
        };
    }

    /**
     * Get template file path
     */
    protected function getTemplateFile(string $protocol, bool $isV7): string
    {
        $version = $isV7 ? 'v7' : 'v6';
        return "vpn-templates/{$protocol}-{$version}.rsc";
    }

    /**
     * Process template with variables
     */
    protected function processTemplate(string $template, array $settings, Router $router): string
    {
        $replacements = [
            '{{ROUTER_NAME}}' => $router->name,
            '{{ROUTER_IP}}' => $router->ip_address,
            '{{LOCAL_ADDRESS}}' => $settings['local_address'] ?? '',
            '{{POOL_NAME}}' => $settings['remote_address_pool'] ?? '',
            '{{POOL_START}}' => $settings['pool_start'] ?? '',
            '{{POOL_END}}' => $settings['pool_end'] ?? '',
            '{{DNS_SERVER}}' => $settings['dns_server'] ?? '8.8.8.8',
            '{{IPSEC_SECRET}}' => $settings['ipsec_secret'] ?? '',
            '{{PROFILE}}' => $settings['default_profile'] ?? 'default-encryption',
            '{{CERTIFICATE}}' => $settings['certificate'] ?? '',
            '{{WG_PORT}}' => $settings['listen_port'] ?? '13231',
            '{{WG_MTU}}' => $settings['mtu'] ?? '1420',
            '{{WG_ADDRESS}}' => $settings['interface_address'] ?? '',
            '{{DATE}}' => now()->format('Y-m-d H:i:s'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Generate L2TP/IPSec script
     */
    protected function generateL2tpScript(array $settings, Router $router, bool $isV7): string
    {
        $script = "# L2TP/IPSec VPN Configuration\n";
        $script .= "# Generated for: {$router->name}\n";
        $script .= "# Date: " . now()->format('Y-m-d H:i:s') . "\n\n";

        // Create IP Pool
        $script .= "# Create IP Pool\n";
        $script .= "/ip pool add name={$settings['remote_address_pool']} ranges={$settings['pool_start']}-{$settings['pool_end']}\n\n";

        // Create PPP Profile
        $script .= "# Create PPP Profile\n";
        $script .= "/ppp profile add name=vpn-l2tp local-address={$settings['local_address']} ";
        $script .= "remote-address={$settings['remote_address_pool']} dns-server={$settings['dns_server']}\n\n";

        // Enable L2TP Server
        $script .= "# Enable L2TP Server\n";
        $script .= "/interface l2tp-server server set enabled=yes default-profile=vpn-l2tp ";
        $script .= "authentication=mschap2 use-ipsec=required ipsec-secret={$settings['ipsec_secret']}\n\n";

        // IPSec Proposal (different for v6 and v7)
        if ($isV7) {
            $script .= "# IPSec Proposal (RouterOS v7)\n";
            $script .= "/ip ipsec proposal set [ find default=yes ] enc-algorithms=aes-256-cbc,aes-128-cbc ";
            $script .= "pfs-group=modp2048\n\n";
        } else {
            $script .= "# IPSec Proposal (RouterOS v6)\n";
            $script .= "/ip ipsec proposal set [ find default=yes ] enc-algorithms=aes-256-cbc,aes-128-cbc,3des ";
            $script .= "pfs-group=modp1024\n\n";
        }

        // Firewall rules
        $script .= "# Firewall Rules\n";
        $script .= "/ip firewall filter add chain=input protocol=udp dst-port=500,4500,1701 action=accept comment=\"Allow L2TP/IPSec\"\n";
        $script .= "/ip firewall filter add chain=input protocol=ipsec-esp action=accept comment=\"Allow IPSec-ESP\"\n";

        return $script;
    }

    /**
     * Generate PPTP script
     */
    protected function generatePptpScript(array $settings, Router $router, bool $isV7): string
    {
        $script = "# PPTP VPN Configuration\n";
        $script .= "# Generated for: {$router->name}\n";
        $script .= "# Date: " . now()->format('Y-m-d H:i:s') . "\n";
        $script .= "# WARNING: PPTP is considered insecure. Use L2TP/IPSec or WireGuard instead.\n\n";

        // Create IP Pool
        $script .= "# Create IP Pool\n";
        $script .= "/ip pool add name={$settings['remote_address_pool']} ranges={$settings['pool_start']}-{$settings['pool_end']}\n\n";

        // Create PPP Profile
        $script .= "# Create PPP Profile\n";
        $script .= "/ppp profile add name=vpn-pptp local-address={$settings['local_address']} ";
        $script .= "remote-address={$settings['remote_address_pool']} dns-server={$settings['dns_server']}\n\n";

        // Enable PPTP Server
        $script .= "# Enable PPTP Server\n";
        $script .= "/interface pptp-server server set enabled=yes default-profile=vpn-pptp authentication=mschap2\n\n";

        // Firewall rules
        $script .= "# Firewall Rules\n";
        $script .= "/ip firewall filter add chain=input protocol=tcp dst-port=1723 action=accept comment=\"Allow PPTP\"\n";
        $script .= "/ip firewall filter add chain=input protocol=gre action=accept comment=\"Allow GRE for PPTP\"\n";

        return $script;
    }

    /**
     * Generate SSTP script
     */
    protected function generateSstpScript(array $settings, Router $router, bool $isV7): string
    {
        $script = "# SSTP VPN Configuration\n";
        $script .= "# Generated for: {$router->name}\n";
        $script .= "# Date: " . now()->format('Y-m-d H:i:s') . "\n";
        $script .= "# NOTE: Requires SSL certificate to be configured\n\n";

        // Create IP Pool
        $script .= "# Create IP Pool\n";
        $script .= "/ip pool add name={$settings['remote_address_pool']} ranges={$settings['pool_start']}-{$settings['pool_end']}\n\n";

        // Create PPP Profile
        $script .= "# Create PPP Profile\n";
        $script .= "/ppp profile add name=vpn-sstp local-address={$settings['local_address']} ";
        $script .= "remote-address={$settings['remote_address_pool']} dns-server={$settings['dns_server']}\n\n";

        // Enable SSTP Server
        $script .= "# Enable SSTP Server\n";
        if (!empty($settings['certificate'])) {
            $script .= "/interface sstp-server server set enabled=yes default-profile=vpn-sstp ";
            $script .= "certificate={$settings['certificate']} authentication=mschap2\n\n";
        } else {
            $script .= "# Certificate not specified - please configure manually\n";
            $script .= "/interface sstp-server server set enabled=yes default-profile=vpn-sstp ";
            $script .= "certificate=<YOUR_CERTIFICATE> authentication=mschap2\n\n";
        }

        // Firewall rules
        $script .= "# Firewall Rules\n";
        $script .= "/ip firewall filter add chain=input protocol=tcp dst-port=443 action=accept comment=\"Allow SSTP\"\n";

        return $script;
    }

    /**
     * Generate WireGuard script (RouterOS v7 only)
     */
    protected function generateWireguardScript(array $settings, Router $router): string
    {
        $script = "# WireGuard VPN Configuration\n";
        $script .= "# Generated for: {$router->name}\n";
        $script .= "# Date: " . now()->format('Y-m-d H:i:s') . "\n";
        $script .= "# NOTE: WireGuard requires RouterOS v7 or later\n\n";

        // Create WireGuard Interface
        $script .= "# Create WireGuard Interface\n";
        $script .= "/interface wireguard add name=wg0 listen-port={$settings['listen_port']} mtu={$settings['mtu']}\n\n";

        // Assign IP Address
        $script .= "# Assign IP Address\n";
        $script .= "/ip address add address={$settings['interface_address']} interface=wg0\n\n";

        // Firewall rules
        $script .= "# Firewall Rules\n";
        $script .= "/ip firewall filter add chain=input protocol=udp dst-port={$settings['listen_port']} action=accept comment=\"Allow WireGuard\"\n\n";

        // Instructions
        $script .= "# After running this script:\n";
        $script .= "# 1. Get the public key: /interface wireguard print\n";
        $script .= "# 2. Add peers: /interface wireguard peers add interface=wg0 public-key=<PEER_PUBLIC_KEY> allowed-address=<PEER_IP>/32\n";

        return $script;
    }

    /**
     * Check if router is running RouterOS v7+
     */
    protected function isRouterV7(Router $router): bool
    {
        if (!$router->version) {
            return false;
        }

        preg_match('/^(\d+)/', $router->version, $matches);
        return isset($matches[1]) && (int) $matches[1] >= 7;
    }
}
