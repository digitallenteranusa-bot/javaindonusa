<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Models\Setting;
use App\Models\VpnServerClient;
use App\Services\VpnServer\OpenVpnService;
use App\Services\VpnServer\WireGuardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VpnServerController extends Controller
{
    public function __construct(
        protected OpenVpnService $openVpnService,
        protected WireGuardService $wireGuardService
    ) {}

    // ================================================================
    // MAIN DASHBOARD
    // ================================================================

    /**
     * VPN Server dashboard
     */
    public function index(Request $request)
    {
        $query = VpnServerClient::with('router');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('client_vpn_ip', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('protocol')) {
            $query->where('protocol', $request->protocol);
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'enabled' => $query->where('is_enabled', true),
                'disabled' => $query->where('is_enabled', false),
                default => null,
            };
        }

        $clients = $query->orderBy('name')->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Check installation status
        $openVpnStatus = $this->openVpnService->checkInstallation();
        $wireGuardStatus = $this->wireGuardService->checkInstallation();

        // Get settings
        $settings = Setting::vpnServer();

        // Statistics
        $stats = [
            'total_clients' => VpnServerClient::count(),
            'openvpn_clients' => VpnServerClient::openVpn()->count(),
            'wireguard_clients' => VpnServerClient::wireGuard()->count(),
            'enabled_clients' => VpnServerClient::enabled()->count(),
        ];

        return Inertia::render('Admin/VpnServer/Index', [
            'clients' => $clients,
            'filters' => $request->only(['search', 'protocol', 'status']),
            'openVpnStatus' => $openVpnStatus,
            'wireGuardStatus' => $wireGuardStatus,
            'settings' => $settings,
            'stats' => $stats,
        ]);
    }

    // ================================================================
    // SETTINGS
    // ================================================================

    /**
     * Show settings page
     */
    public function settings()
    {
        $settings = Setting::vpnServer();
        $openVpnStatus = $this->openVpnService->checkInstallation();
        $wireGuardStatus = $this->wireGuardService->checkInstallation();

        return Inertia::render('Admin/VpnServer/Settings', [
            'settings' => $settings,
            'openVpnStatus' => $openVpnStatus,
            'wireGuardStatus' => $wireGuardStatus,
        ]);
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'public_endpoint' => 'required|string|max:255',
            'wg_server_address' => 'required|string|max:50',
            'openvpn_server_address' => 'required|string|max:50',
            'port' => 'required|integer|min:1|max:65535',
            'protocol' => 'required|in:udp,tcp',
            'wg_port' => 'required|integer|min:1|max:65535',
        ]);

        foreach ($validated as $key => $value) {
            Setting::setValue('vpn_server', $key, $value);
        }

        // Keep backward compatibility - set server_address to WireGuard subnet
        Setting::setValue('vpn_server', 'server_address', $validated['wg_server_address']);

        return back()->with('success', 'Pengaturan VPN Server berhasil disimpan');
    }

    // ================================================================
    // OPENVPN SETUP
    // ================================================================

    /**
     * Initialize OpenVPN PKI
     */
    public function initPki()
    {
        $result = $this->openVpnService->initializePki();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Generate CA certificate
     */
    public function generateCa()
    {
        $result = $this->openVpnService->generateCaCertificate();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Generate server certificate
     */
    public function generateServerCert()
    {
        $result = $this->openVpnService->generateServerCertificate();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Generate DH parameters
     */
    public function generateDh()
    {
        $result = $this->openVpnService->generateDhParams();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Generate TLS auth key
     */
    public function generateTaKey()
    {
        $result = $this->openVpnService->generateTaKey();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    // ================================================================
    // WIREGUARD SETUP
    // ================================================================

    /**
     * Generate WireGuard server keys
     */
    public function generateWgKeys()
    {
        $keys = $this->wireGuardService->ensureServerKeys();

        return back()->with('success', 'WireGuard server keys generated');
    }

    // ================================================================
    // SERVICE CONTROL
    // ================================================================

    /**
     * Start OpenVPN service
     */
    public function startOpenVpn()
    {
        $result = $this->openVpnService->startService();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Stop OpenVPN service
     */
    public function stopOpenVpn()
    {
        $result = $this->openVpnService->stopService();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Restart OpenVPN service
     */
    public function restartOpenVpn()
    {
        $result = $this->openVpnService->restartService();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Start WireGuard interface
     */
    public function startWireGuard()
    {
        $result = $this->wireGuardService->startInterface();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Stop WireGuard interface
     */
    public function stopWireGuard()
    {
        $result = $this->wireGuardService->stopInterface();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Sync WireGuard config
     */
    public function syncWireGuard()
    {
        $result = $this->wireGuardService->syncConfig();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    // ================================================================
    // CLIENT MANAGEMENT
    // ================================================================

    /**
     * Show create client form
     */
    public function createClient()
    {
        $routers = Router::active()->orderBy('name')->get(['id', 'name', 'ip_address']);

        return Inertia::render('Admin/VpnServer/ClientForm', [
            'client' => null,
            'routers' => $routers,
        ]);
    }

    /**
     * Store new client
     */
    public function storeClient(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('vpn_server_clients')->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:255',
            'protocol' => 'required|in:openvpn,wireguard',
            'router_id' => 'nullable|exists:routers,id',
            'mikrotik_lan_subnet' => 'nullable|string|max:50',
        ]);

        if ($validated['protocol'] === 'wireguard') {
            $keys = $this->wireGuardService->generateKeyPair();
            $clientIp = $this->wireGuardService->getNextAvailableClientIp();

            $client = VpnServerClient::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'protocol' => 'wireguard',
                'public_key' => $keys['public_key'],
                'private_key' => $keys['private_key'],
                'client_vpn_ip' => $clientIp,
                'router_id' => $validated['router_id'],
                'mikrotik_lan_subnet' => $validated['mikrotik_lan_subnet'],
            ]);

            // Generate and store script
            $client->update([
                'generated_script' => $this->wireGuardService->generateMikrotikScript($client),
                'last_generated_at' => now(),
            ]);

            // Sync WireGuard config
            $this->wireGuardService->syncConfig();

        } else {
            // OpenVPN
            $commonName = 'client-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $validated['name']));
            $clientIp = $this->openVpnService->getNextAvailableClientIp();

            // Generate certificate
            $certResult = $this->openVpnService->generateClientCertificate($commonName);

            if (!$certResult['success']) {
                return back()->with('error', 'Gagal membuat sertifikat: ' . ($certResult['message'] ?? 'Unknown error'));
            }

            $client = VpnServerClient::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'protocol' => 'openvpn',
                'common_name' => $commonName,
                'client_vpn_ip' => $clientIp,
                'router_id' => $validated['router_id'],
                'mikrotik_lan_subnet' => $validated['mikrotik_lan_subnet'],
            ]);

            // Generate and store configs
            $client->update([
                'generated_config' => $this->openVpnService->generateClientConfig($client),
                'generated_script' => $this->openVpnService->generateMikrotikScript($client),
                'last_generated_at' => now(),
            ]);

            // Write CCD file
            $this->openVpnService->writeClientCcd($client);

            // Restart OpenVPN to apply new routes
            $this->openVpnService->restartService();
        }

        return redirect()->route('admin.vpn-server.index')
            ->with('success', 'VPN Client berhasil dibuat');
    }

    /**
     * Show client detail
     */
    public function showClient(VpnServerClient $client)
    {
        $client->load('router');

        return Inertia::render('Admin/VpnServer/ClientShow', [
            'client' => $client->makeVisible(['generated_config', 'private_key']),
        ]);
    }

    /**
     * Show edit client form
     */
    public function editClient(VpnServerClient $client)
    {
        $routers = Router::active()->orderBy('name')->get(['id', 'name', 'ip_address']);

        return Inertia::render('Admin/VpnServer/ClientForm', [
            'client' => $client,
            'routers' => $routers,
        ]);
    }

    /**
     * Update client
     */
    public function updateClient(Request $request, VpnServerClient $client)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('vpn_server_clients')->ignore($client->id)],
            'description' => 'nullable|string|max:255',
            'router_id' => 'nullable|exists:routers,id',
            'mikrotik_lan_subnet' => 'nullable|string|max:50',
        ]);

        $client->update($validated);

        // Regenerate script
        if ($client->isWireGuard()) {
            $client->update([
                'generated_script' => $this->wireGuardService->generateMikrotikScript($client),
                'last_generated_at' => now(),
            ]);
            $this->wireGuardService->syncConfig();
        } else {
            $client->update([
                'generated_config' => $this->openVpnService->generateClientConfig($client),
                'generated_script' => $this->openVpnService->generateMikrotikScript($client),
                'last_generated_at' => now(),
            ]);
            $this->openVpnService->writeClientCcd($client);
            $this->openVpnService->restartService();
        }

        return redirect()->route('admin.vpn-server.index')
            ->with('success', 'VPN Client berhasil diperbarui');
    }

    /**
     * Delete client (permanent delete)
     */
    public function destroyClient(VpnServerClient $client)
    {
        $clientName = $client->name;
        $isWireGuard = $client->isWireGuard();

        // Revoke OpenVPN certificate if applicable
        if ($client->isOpenVpn() && $client->common_name) {
            $this->openVpnService->revokeClientCertificate($client->common_name);
        }

        // Permanent delete so name and common_name can be reused
        $client->forceDelete();

        // Sync config
        if ($isWireGuard) {
            $this->wireGuardService->syncConfig();
        } else {
            $this->openVpnService->restartService();
        }

        return redirect()->route('admin.vpn-server.index')
            ->with('success', "VPN Client '{$clientName}' berhasil dihapus");
    }

    /**
     * Toggle client enabled status
     */
    public function toggleClient(VpnServerClient $client)
    {
        $client->update(['is_enabled' => !$client->is_enabled]);

        // Sync config
        if ($client->isWireGuard()) {
            $this->wireGuardService->syncConfig();
        } else {
            $this->openVpnService->restartService();
        }

        $status = $client->is_enabled ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Client berhasil {$status}");
    }

    /**
     * Regenerate client config/keys
     */
    public function regenerateClient(VpnServerClient $client)
    {
        if ($client->isWireGuard()) {
            $keys = $this->wireGuardService->generateKeyPair();

            $client->update([
                'public_key' => $keys['public_key'],
                'private_key' => $keys['private_key'],
                'generated_script' => $this->wireGuardService->generateMikrotikScript($client),
                'last_generated_at' => now(),
            ]);

            $this->wireGuardService->syncConfig();
        } else {
            // Revoke old cert
            if ($client->common_name) {
                $this->openVpnService->revokeClientCertificate($client->common_name);
            }

            // Generate new cert
            $result = $this->openVpnService->generateClientCertificate($client->common_name);

            if (!$result['success']) {
                return back()->with('error', 'Gagal regenerate sertifikat: ' . ($result['message'] ?? 'Unknown error'));
            }

            $client->update([
                'generated_config' => $this->openVpnService->generateClientConfig($client),
                'generated_script' => $this->openVpnService->generateMikrotikScript($client),
                'last_generated_at' => now(),
            ]);

            $this->openVpnService->writeClientCcd($client);
            $this->openVpnService->restartService();
        }

        return back()->with('success', 'Client config berhasil di-regenerate');
    }

    // ================================================================
    // DOWNLOADS
    // ================================================================

    /**
     * Download client config (OpenVPN .ovpn file)
     */
    public function downloadConfig(VpnServerClient $client)
    {
        if (!$client->isOpenVpn()) {
            return back()->with('error', 'Config download hanya untuk OpenVPN');
        }

        $config = $client->generated_config ?? $this->openVpnService->generateClientConfig($client);

        return response($config)
            ->header('Content-Type', 'application/x-openvpn-profile')
            ->header('Content-Disposition', 'attachment; filename="' . $client->common_name . '.ovpn"');
    }

    /**
     * Download Mikrotik script
     */
    public function downloadScript(VpnServerClient $client)
    {
        $script = $client->generated_script;

        if (!$script) {
            $script = $client->isWireGuard()
                ? $this->wireGuardService->generateMikrotikScript($client)
                : $this->openVpnService->generateMikrotikScript($client);
        }

        $filename = 'mikrotik-' . $client->protocol . '-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $client->name)) . '.rsc';

        return response($script)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    // ================================================================
    // STATUS & MONITORING
    // ================================================================

    /**
     * Refresh client statuses
     */
    public function refreshStatus()
    {
        $this->openVpnService->updateClientStatuses();
        $this->wireGuardService->updateClientStatuses();

        return back()->with('success', 'Status client berhasil diperbarui');
    }

    /**
     * Get live status (API)
     */
    public function liveStatus()
    {
        $openVpnClients = $this->openVpnService->getConnectedClients();
        $wireGuardClients = $this->wireGuardService->getConnectedClients();

        return response()->json([
            'openvpn' => [
                'service' => $this->openVpnService->getServiceStatus(),
                'connected' => $openVpnClients,
            ],
            'wireguard' => [
                'interface' => $this->wireGuardService->getInterfaceStatus(),
                'connected' => $wireGuardClients,
            ],
        ]);
    }
}
