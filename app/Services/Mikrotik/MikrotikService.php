<?php

namespace App\Services\Mikrotik;

use App\Models\Router;
use App\Models\Customer;
use App\Models\BillingLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MikrotikService
{
    protected ?RouterOSClient $client = null;
    protected ?Router $router = null;

    // ================================================================
    // CONNECTION MANAGEMENT
    // ================================================================

    /**
     * Connect to a router
     */
    public function connect(Router $router): bool
    {
        try {
            $this->router = $router;

            $this->client = new RouterOSClient(
                $router->ip_address,
                $router->username,
                $router->password,
                $router->api_port ?? 8728,
                config('mikrotik.timeout', 10)
            );

            $connected = $this->client->connect();

            if ($connected) {
                // Update router last connected time
                $router->update([
                    'last_connected_at' => now(),
                ]);
            }

            return $connected;
        } catch (\Exception $e) {
            Log::error('Mikrotik connection failed', [
                'router' => $router->name,
                'ip' => $router->ip_address,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Disconnect from router
     */
    public function disconnect(): void
    {
        if ($this->client) {
            $this->client->disconnect();
            $this->client = null;
        }
        $this->router = null;
    }

    /**
     * Connect to router by ID
     */
    public function connectById(int $routerId): bool
    {
        $router = Router::findOrFail($routerId);
        return $this->connect($router);
    }

    /**
     * Execute callback with router connection
     */
    public function withRouter(Router $router, callable $callback)
    {
        try {
            $this->connect($router);
            return $callback($this);
        } finally {
            $this->disconnect();
        }
    }

    // ================================================================
    // ROUTER INFO & STATUS
    // ================================================================

    /**
     * Get router identity and info
     */
    public function getRouterInfo(): array
    {
        $this->ensureConnected();

        $identity = $this->client->command('/system/identity/print');
        $resource = $this->client->command('/system/resource/print');
        $routerboard = $this->client->command('/system/routerboard/print');

        return [
            'identity' => $identity[0]['name'] ?? 'Unknown',
            'version' => $resource[0]['version'] ?? 'Unknown',
            'uptime' => $resource[0]['uptime'] ?? 'Unknown',
            'cpu_load' => $resource[0]['cpu-load'] ?? 0,
            'free_memory' => $resource[0]['free-memory'] ?? 0,
            'total_memory' => $resource[0]['total-memory'] ?? 0,
            'free_hdd' => $resource[0]['free-hdd-space'] ?? 0,
            'total_hdd' => $resource[0]['total-hdd-space'] ?? 0,
            'architecture' => $resource[0]['architecture-name'] ?? 'Unknown',
            'board_name' => $resource[0]['board-name'] ?? 'Unknown',
            'model' => $routerboard[0]['model'] ?? 'Unknown',
            'serial' => $routerboard[0]['serial-number'] ?? 'Unknown',
        ];
    }

    /**
     * Get router identity name
     */
    public function getIdentity(): string
    {
        $this->ensureConnected();

        $identity = $this->client->command('/system/identity/print');
        return $identity[0]['name'] ?? 'Unknown';
    }

    /**
     * Get router resources
     */
    public function getResources(): array
    {
        $this->ensureConnected();

        $result = $this->client->command('/system/resource/print');
        return $result[0] ?? [];
    }

    /**
     * Update router status in database
     */
    public function updateRouterStatus(Router $router): void
    {
        try {
            $this->connect($router);
            $info = $this->getRouterInfo();

            $router->update([
                'identity' => $info['identity'],
                'version' => $info['version'],
                'uptime' => $info['uptime'],
                'cpu_load' => $info['cpu_load'],
                'memory_usage' => $this->calculateMemoryUsage(
                    $info['free_memory'],
                    $info['total_memory']
                ),
                'model' => $info['model'],
                'serial_number' => $info['serial'],
                'last_connected_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update router status', [
                'router' => $router->name,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->disconnect();
        }
    }

    protected function calculateMemoryUsage($free, $total): int
    {
        if ($total == 0) return 0;
        return (int) round((($total - $free) / $total) * 100);
    }

    // ================================================================
    // PPPoE SECRET MANAGEMENT
    // ================================================================

    /**
     * Get all PPPoE secrets
     */
    public function getPPPoESecrets(?string $filter = null): array
    {
        $this->ensureConnected();

        $params = [];
        if ($filter) {
            $params[] = '?name=' . $filter;
        }

        return $this->client->command('/ppp/secret/print', $params);
    }

    /**
     * Find PPPoE secret by username
     */
    public function findPPPoESecret(string $username): ?array
    {
        $this->ensureConnected();

        $result = $this->client->command('/ppp/secret/print', [
            '?name=' . $username,
        ]);

        return $result[0] ?? null;
    }

    /**
     * Create PPPoE secret
     */
    public function createPPPoESecret(array $data): array
    {
        $this->ensureConnected();

        $params = [
            'name' => $data['username'],
            'password' => $data['password'],
            'service' => $data['service'] ?? 'pppoe',
            'profile' => $data['profile'] ?? 'default',
        ];

        if (!empty($data['local_address'])) {
            $params['local-address'] = $data['local_address'];
        }

        if (!empty($data['remote_address'])) {
            $params['remote-address'] = $data['remote_address'];
        }

        if (!empty($data['comment'])) {
            $params['comment'] = $data['comment'];
        }

        $this->client->command('/ppp/secret/add', $params);

        return ['success' => true, 'message' => 'PPPoE secret created'];
    }

    /**
     * Update PPPoE secret
     */
    public function updatePPPoESecret(string $username, array $data): array
    {
        $this->ensureConnected();

        $secret = $this->findPPPoESecret($username);
        if (!$secret) {
            return ['success' => false, 'message' => 'Secret not found'];
        }

        $params = ['.id' => $secret['.id']];

        if (isset($data['password'])) {
            $params['password'] = $data['password'];
        }

        if (isset($data['profile'])) {
            $params['profile'] = $data['profile'];
        }

        if (isset($data['disabled'])) {
            $params['disabled'] = $data['disabled'] ? 'yes' : 'no';
        }

        if (isset($data['comment'])) {
            $params['comment'] = $data['comment'];
        }

        $this->client->command('/ppp/secret/set', $params);

        return ['success' => true, 'message' => 'PPPoE secret updated'];
    }

    /**
     * Delete PPPoE secret
     */
    public function deletePPPoESecret(string $username): array
    {
        $this->ensureConnected();

        $secret = $this->findPPPoESecret($username);
        if (!$secret) {
            return ['success' => false, 'message' => 'Secret not found'];
        }

        $this->client->command('/ppp/secret/remove', ['.id' => $secret['.id']]);

        return ['success' => true, 'message' => 'PPPoE secret deleted'];
    }

    /**
     * Enable PPPoE secret
     */
    public function enablePPPoESecret(string $username): array
    {
        return $this->updatePPPoESecret($username, ['disabled' => false]);
    }

    /**
     * Disable PPPoE secret
     */
    public function disablePPPoESecret(string $username): array
    {
        return $this->updatePPPoESecret($username, ['disabled' => true]);
    }

    /**
     * Change PPPoE profile (for bandwidth limiting)
     */
    public function changePPPoEProfile(string $username, string $profile): array
    {
        return $this->updatePPPoESecret($username, ['profile' => $profile]);
    }

    // ================================================================
    // PPPoE ACTIVE CONNECTIONS
    // ================================================================

    /**
     * Get active PPPoE connections
     */
    public function getActiveConnections(): array
    {
        $this->ensureConnected();

        return $this->client->command('/ppp/active/print');
    }

    /**
     * Find active connection by username
     */
    public function findActiveConnection(string $username): ?array
    {
        $this->ensureConnected();

        $result = $this->client->command('/ppp/active/print', [
            '?name=' . $username,
        ]);

        return $result[0] ?? null;
    }

    /**
     * Disconnect active PPPoE session
     */
    public function disconnectPPPoE(string $username): array
    {
        $this->ensureConnected();

        $active = $this->findActiveConnection($username);
        if (!$active) {
            return ['success' => false, 'message' => 'No active connection'];
        }

        $this->client->command('/ppp/active/remove', ['.id' => $active['.id']]);

        return ['success' => true, 'message' => 'Connection disconnected'];
    }

    // ================================================================
    // ADDRESS LIST MANAGEMENT (FOR ISOLATION)
    // ================================================================

    /**
     * Get address list entries
     */
    public function getAddressList(string $list): array
    {
        $this->ensureConnected();

        return $this->client->command('/ip/firewall/address-list/print', [
            '?list=' . $list,
        ]);
    }

    /**
     * Add IP to address list
     */
    public function addToAddressList(string $ip, string $list, ?string $comment = null, ?string $timeout = null): array
    {
        $this->ensureConnected();

        // Check if already exists
        $existing = $this->client->command('/ip/firewall/address-list/print', [
            '?list=' . $list,
            '?address=' . $ip,
        ]);

        if (!empty($existing)) {
            return ['success' => true, 'message' => 'IP already in list'];
        }

        $params = [
            'list' => $list,
            'address' => $ip,
        ];

        if ($comment) {
            $params['comment'] = $comment;
        }

        if ($timeout) {
            $params['timeout'] = $timeout;
        }

        $this->client->command('/ip/firewall/address-list/add', $params);

        return ['success' => true, 'message' => 'IP added to address list'];
    }

    /**
     * Remove IP from address list
     */
    public function removeFromAddressList(string $ip, string $list): array
    {
        $this->ensureConnected();

        $entries = $this->client->command('/ip/firewall/address-list/print', [
            '?list=' . $list,
            '?address=' . $ip,
        ]);

        if (empty($entries)) {
            return ['success' => true, 'message' => 'IP not in list'];
        }

        foreach ($entries as $entry) {
            $this->client->command('/ip/firewall/address-list/remove', [
                '.id' => $entry['.id'],
            ]);
        }

        return ['success' => true, 'message' => 'IP removed from address list'];
    }

    // ================================================================
    // CUSTOMER ISOLATION
    // ================================================================

    /**
     * Isolate customer (block internet access)
     */
    public function isolateCustomer(Customer $customer): array
    {
        $router = $customer->router;
        if (!$router) {
            return ['success' => false, 'message' => 'No router assigned'];
        }

        try {
            $this->connect($router);

            $isolationMethod = config('mikrotik.isolation.method', 'address_list');
            $result = [];

            switch ($isolationMethod) {
                case 'address_list':
                    // Add to isolation address list
                    $addressList = config('mikrotik.isolation.address_list', 'isolir');

                    if ($customer->ip_address) {
                        $result = $this->addToAddressList(
                            $customer->ip_address,
                            $addressList,
                            "Isolir: {$customer->customer_id} - {$customer->name}"
                        );
                    }

                    // Also disconnect active session
                    if ($customer->pppoe_username) {
                        $this->disconnectPPPoE($customer->pppoe_username);
                    }
                    break;

                case 'profile':
                    // Change to isolation profile
                    $isolationProfile = config('mikrotik.isolation.profile', 'isolir');

                    if ($customer->pppoe_username) {
                        $result = $this->changePPPoEProfile($customer->pppoe_username, $isolationProfile);
                        $this->disconnectPPPoE($customer->pppoe_username);
                    }
                    break;

                case 'disable':
                    // Disable PPPoE secret
                    if ($customer->pppoe_username) {
                        $result = $this->disablePPPoESecret($customer->pppoe_username);
                        $this->disconnectPPPoE($customer->pppoe_username);
                    }
                    break;

                default:
                    return ['success' => false, 'message' => 'Unknown isolation method'];
            }

            // Log the action
            BillingLog::logCustomer($customer, 'customer_isolated', "Isolated via {$isolationMethod}");

            return $result;
        } catch (\Exception $e) {
            Log::error('Customer isolation failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Reopen customer access
     */
    public function reopenCustomer(Customer $customer): array
    {
        $router = $customer->router;
        if (!$router) {
            return ['success' => false, 'message' => 'No router assigned'];
        }

        try {
            $this->connect($router);

            $isolationMethod = config('mikrotik.isolation.method', 'address_list');
            $result = [];

            switch ($isolationMethod) {
                case 'address_list':
                    // Remove from isolation address list
                    $addressList = config('mikrotik.isolation.address_list', 'isolir');

                    if ($customer->ip_address) {
                        $result = $this->removeFromAddressList($customer->ip_address, $addressList);
                    }
                    break;

                case 'profile':
                    // Restore original profile
                    $originalProfile = $customer->package?->mikrotik_profile ?? 'default';

                    if ($customer->pppoe_username) {
                        $result = $this->changePPPoEProfile($customer->pppoe_username, $originalProfile);
                    }
                    break;

                case 'disable':
                    // Enable PPPoE secret
                    if ($customer->pppoe_username) {
                        $result = $this->enablePPPoESecret($customer->pppoe_username);
                    }
                    break;

                default:
                    return ['success' => false, 'message' => 'Unknown isolation method'];
            }

            // Log the action
            BillingLog::logCustomer($customer, 'customer_reopened', "Access reopened via {$isolationMethod}");

            return $result;
        } catch (\Exception $e) {
            Log::error('Customer reopen failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Check if customer is isolated
     */
    public function isCustomerIsolated(Customer $customer): bool
    {
        $router = $customer->router;
        if (!$router) {
            return false;
        }

        try {
            $this->connect($router);

            $isolationMethod = config('mikrotik.isolation.method', 'address_list');

            switch ($isolationMethod) {
                case 'address_list':
                    $addressList = config('mikrotik.isolation.address_list', 'isolir');

                    if ($customer->ip_address) {
                        $entries = $this->client->command('/ip/firewall/address-list/print', [
                            '?list=' . $addressList,
                            '?address=' . $customer->ip_address,
                        ]);
                        return !empty($entries);
                    }
                    break;

                case 'profile':
                    $isolationProfile = config('mikrotik.isolation.profile', 'isolir');

                    if ($customer->pppoe_username) {
                        $secret = $this->findPPPoESecret($customer->pppoe_username);
                        return $secret && ($secret['profile'] ?? '') === $isolationProfile;
                    }
                    break;

                case 'disable':
                    if ($customer->pppoe_username) {
                        $secret = $this->findPPPoESecret($customer->pppoe_username);
                        return $secret && ($secret['disabled'] ?? 'false') === 'true';
                    }
                    break;
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('Failed to check isolation status', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        } finally {
            $this->disconnect();
        }
    }

    // ================================================================
    // BULK OPERATIONS
    // ================================================================

    /**
     * Bulk isolate customers
     */
    public function bulkIsolate(array $customerIds): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        // Group customers by router
        $customers = Customer::whereIn('id', $customerIds)
            ->with('router', 'package')
            ->get()
            ->groupBy('router_id');

        foreach ($customers as $routerId => $routerCustomers) {
            if (!$routerId) continue;

            $router = Router::find($routerId);
            if (!$router) continue;

            try {
                $this->connect($router);

                foreach ($routerCustomers as $customer) {
                    try {
                        $this->isolateCustomerInternal($customer);
                        $results['success']++;
                    } catch (\Exception $e) {
                        $results['failed']++;
                        $results['errors'][] = [
                            'customer_id' => $customer->id,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Router connection failed
                $results['failed'] += $routerCustomers->count();
                $results['errors'][] = [
                    'router' => $router->name,
                    'error' => $e->getMessage(),
                ];
            } finally {
                $this->disconnect();
            }
        }

        return $results;
    }

    /**
     * Internal isolate (assumes already connected)
     */
    protected function isolateCustomerInternal(Customer $customer): void
    {
        $isolationMethod = config('mikrotik.isolation.method', 'address_list');

        switch ($isolationMethod) {
            case 'address_list':
                $addressList = config('mikrotik.isolation.address_list', 'isolir');
                if ($customer->ip_address) {
                    $this->addToAddressList(
                        $customer->ip_address,
                        $addressList,
                        "Isolir: {$customer->customer_id}"
                    );
                }
                if ($customer->pppoe_username) {
                    $this->disconnectPPPoE($customer->pppoe_username);
                }
                break;

            case 'profile':
                $isolationProfile = config('mikrotik.isolation.profile', 'isolir');
                if ($customer->pppoe_username) {
                    $this->changePPPoEProfile($customer->pppoe_username, $isolationProfile);
                    $this->disconnectPPPoE($customer->pppoe_username);
                }
                break;

            case 'disable':
                if ($customer->pppoe_username) {
                    $this->disablePPPoESecret($customer->pppoe_username);
                    $this->disconnectPPPoE($customer->pppoe_username);
                }
                break;
        }

        BillingLog::logCustomer($customer, 'customer_isolated', "Bulk isolation via {$isolationMethod}");
    }

    // ================================================================
    // PROFILE MANAGEMENT
    // ================================================================

    /**
     * Get all PPP profiles
     */
    public function getProfiles(): array
    {
        $this->ensureConnected();

        return $this->client->command('/ppp/profile/print');
    }

    /**
     * Create PPP profile
     */
    public function createProfile(array $data): array
    {
        $this->ensureConnected();

        $params = [
            'name' => $data['name'],
        ];

        if (!empty($data['local_address'])) {
            $params['local-address'] = $data['local_address'];
        }

        if (!empty($data['remote_address'])) {
            $params['remote-address'] = $data['remote_address'];
        }

        if (!empty($data['rate_limit'])) {
            $params['rate-limit'] = $data['rate_limit'];
        }

        if (!empty($data['address_list'])) {
            $params['address-list'] = $data['address_list'];
        }

        $this->client->command('/ppp/profile/add', $params);

        return ['success' => true, 'message' => 'Profile created'];
    }

    // ================================================================
    // QUEUE MANAGEMENT
    // ================================================================

    /**
     * Get simple queues
     */
    public function getQueues(): array
    {
        $this->ensureConnected();

        return $this->client->command('/queue/simple/print');
    }

    /**
     * Find queue by name
     */
    public function findQueue(string $name): ?array
    {
        $this->ensureConnected();

        $result = $this->client->command('/queue/simple/print', [
            '?name=' . $name,
        ]);

        return $result[0] ?? null;
    }

    /**
     * Create simple queue
     */
    public function createQueue(array $data): array
    {
        $this->ensureConnected();

        $params = [
            'name' => $data['name'],
            'target' => $data['target'],
        ];

        if (!empty($data['max_limit'])) {
            $params['max-limit'] = $data['max_limit'];
        }

        if (!empty($data['burst_limit'])) {
            $params['burst-limit'] = $data['burst_limit'];
        }

        if (!empty($data['burst_threshold'])) {
            $params['burst-threshold'] = $data['burst_threshold'];
        }

        if (!empty($data['burst_time'])) {
            $params['burst-time'] = $data['burst_time'];
        }

        if (!empty($data['comment'])) {
            $params['comment'] = $data['comment'];
        }

        $this->client->command('/queue/simple/add', $params);

        return ['success' => true, 'message' => 'Queue created'];
    }

    // ================================================================
    // HELPERS
    // ================================================================

    protected function ensureConnected(): void
    {
        if (!$this->client || !$this->client->isConnected()) {
            throw new \Exception('Not connected to router');
        }
    }

    /**
     * Get current router
     */
    public function getCurrentRouter(): ?Router
    {
        return $this->router;
    }

    /**
     * Get client
     */
    public function getClient(): ?RouterOSClient
    {
        return $this->client;
    }
}
