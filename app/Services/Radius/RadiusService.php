<?php

namespace App\Services\Radius;

use App\Models\Customer;
use App\Models\Radius\Nas;
use App\Models\Radius\RadAcct;
use App\Models\Radius\RadCheck;
use App\Models\Radius\RadReply;
use App\Models\Radius\RadUserGroup;
use App\Models\RadiusServer;
use App\Models\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RadiusService
{
    public function isEnabled(): bool
    {
        return (bool) config('radius.enabled');
    }

    /**
     * Sync customer credentials to RADIUS DB (radcheck + radreply + radusergroup).
     */
    public function syncCustomer(Customer $customer): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $username = $customer->pppoe_username;
        if (empty($username)) {
            return false;
        }

        try {
            DB::connection('radius')->transaction(function () use ($customer, $username) {
                // Clear existing entries
                RadCheck::forUser($username)->delete();
                RadReply::forUser($username)->delete();
                RadUserGroup::forUser($username)->delete();

                // Insert Cleartext-Password
                RadCheck::create([
                    'username' => $username,
                    'attribute' => 'Cleartext-Password',
                    'op' => ':=',
                    'value' => $customer->pppoe_password ?? '',
                ]);

                // Insert rate limit from package
                $rateLimit = $customer->package?->mikrotik_rate_limit;
                if ($rateLimit) {
                    RadReply::create([
                        'username' => $username,
                        'attribute' => config('radius.attributes.rate_limit', 'Mikrotik-Rate-Limit'),
                        'op' => ':=',
                        'value' => $rateLimit,
                    ]);
                }

                // Insert default group
                RadUserGroup::create([
                    'username' => $username,
                    'groupname' => config('radius.default_group', 'default'),
                    'priority' => 1,
                ]);
            });

            Log::info('RADIUS: Customer synced', ['username' => $username]);
            return true;
        } catch (\Exception $e) {
            Log::error('RADIUS: Failed to sync customer', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remove customer from RADIUS DB.
     */
    public function removeCustomer(Customer $customer): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $username = $customer->pppoe_username;
        if (empty($username)) {
            return false;
        }

        try {
            DB::connection('radius')->transaction(function () use ($username) {
                RadCheck::forUser($username)->delete();
                RadReply::forUser($username)->delete();
                RadUserGroup::forUser($username)->delete();
            });

            Log::info('RADIUS: Customer removed', ['username' => $username]);
            return true;
        } catch (\Exception $e) {
            Log::error('RADIUS: Failed to remove customer', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Isolate customer — change rate limit to isolation value.
     */
    public function isolateCustomer(Customer $customer): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $username = $customer->pppoe_username;
        if (empty($username)) {
            return false;
        }

        try {
            $method = config('radius.isolation_method', 'rate_limit');

            DB::connection('radius')->transaction(function () use ($username, $method) {
                if ($method === 'delete') {
                    RadCheck::forUser($username)->delete();
                    RadReply::forUser($username)->delete();
                    RadUserGroup::forUser($username)->delete();
                    return;
                }

                if ($method === 'group') {
                    RadUserGroup::forUser($username)->delete();
                    RadUserGroup::create([
                        'username' => $username,
                        'groupname' => config('radius.isolation_group', 'isolated'),
                        'priority' => 1,
                    ]);
                }

                // Always update rate limit for rate_limit method (also as extra measure for group method)
                if ($method === 'rate_limit' || $method === 'group') {
                    $rateLimitAttr = config('radius.attributes.rate_limit', 'Mikrotik-Rate-Limit');
                    RadReply::forUser($username)->where('attribute', $rateLimitAttr)->delete();
                    RadReply::create([
                        'username' => $username,
                        'attribute' => $rateLimitAttr,
                        'op' => ':=',
                        'value' => config('radius.isolation_rate_limit', '1k/1k'),
                    ]);
                }
            });

            Log::info('RADIUS: Customer isolated', ['username' => $username, 'method' => $method]);
            return true;
        } catch (\Exception $e) {
            Log::error('RADIUS: Failed to isolate customer', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Reopen customer — restore rate limit from package.
     */
    public function reopenCustomer(Customer $customer): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $username = $customer->pppoe_username;
        if (empty($username)) {
            return false;
        }

        try {
            DB::connection('radius')->transaction(function () use ($customer, $username) {
                $rateLimitAttr = config('radius.attributes.rate_limit', 'Mikrotik-Rate-Limit');

                // Restore rate limit from package
                RadReply::forUser($username)->where('attribute', $rateLimitAttr)->delete();

                $rateLimit = $customer->package?->mikrotik_rate_limit;
                if ($rateLimit) {
                    RadReply::create([
                        'username' => $username,
                        'attribute' => $rateLimitAttr,
                        'op' => ':=',
                        'value' => $rateLimit,
                    ]);
                }

                // Restore default group
                RadUserGroup::forUser($username)->delete();
                RadUserGroup::create([
                    'username' => $username,
                    'groupname' => config('radius.default_group', 'default'),
                    'priority' => 1,
                ]);
            });

            Log::info('RADIUS: Customer reopened', ['username' => $username]);
            return true;
        } catch (\Exception $e) {
            Log::error('RADIUS: Failed to reopen customer', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync a router to NAS table using its RadiusServer config.
     */
    public function syncNas(Router $router): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $radiusServer = $router->radiusServer;
        if (!$radiusServer || !$radiusServer->isActive()) {
            return false;
        }

        try {
            Nas::updateOrCreate(
                ['nasname' => $router->ip_address],
                [
                    'shortname' => $router->identity ?: $router->ip_address,
                    'type' => 'other',
                    'secret' => $radiusServer->decrypted_secret ?? 'secret',
                    'description' => "Router: {$router->identity}",
                ]
            );

            Log::info('RADIUS: NAS synced', ['nasname' => $router->ip_address]);
            return true;
        } catch (\Exception $e) {
            Log::error('RADIUS: Failed to sync NAS', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remove router from NAS table.
     */
    public function removeNas(Router $router): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            Nas::where('nasname', $router->ip_address)->delete();

            Log::info('RADIUS: NAS removed', ['nasname' => $router->ip_address]);
            return true;
        } catch (\Exception $e) {
            Log::error('RADIUS: Failed to remove NAS', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if customer has active RADIUS session.
     */
    public function isOnline(Customer $customer): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $username = $customer->pppoe_username;
        if (empty($username)) {
            return false;
        }

        try {
            return RadAcct::forUser($username)->active()->exists();
        } catch (\Exception $e) {
            Log::error('RADIUS: Failed to check online status', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get accounting data for customer.
     */
    public function getAccountingData(Customer $customer): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $username = $customer->pppoe_username;
        if (empty($username)) {
            return [];
        }

        try {
            $activeSession = RadAcct::forUser($username)->active()->first();
            $lastSession = RadAcct::forUser($username)
                ->whereNotNull('acctstoptime')
                ->orderByDesc('acctstoptime')
                ->first();

            return [
                'is_online' => $activeSession !== null,
                'active_session' => $activeSession ? [
                    'session_id' => $activeSession->acctsessionid,
                    'start_time' => $activeSession->acctstarttime?->toDateTimeString(),
                    'session_time' => $activeSession->acctsessiontime,
                    'input_octets' => $activeSession->acctinputoctets,
                    'output_octets' => $activeSession->acctoutputoctets,
                    'framed_ip' => $activeSession->framedipaddress,
                    'nas_ip' => $activeSession->nasipaddress,
                ] : null,
                'last_session' => $lastSession ? [
                    'stop_time' => $lastSession->acctstoptime?->toDateTimeString(),
                    'terminate_cause' => $lastSession->acctterminatecause,
                ] : null,
                'total_sessions' => RadAcct::forUser($username)->count(),
            ];
        } catch (\Exception $e) {
            Log::error('RADIUS: Failed to get accounting data', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Bulk sync all active customers with PPPoE credentials.
     */
    public function syncAllCustomers(): array
    {
        if (!$this->isEnabled()) {
            return ['synced' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $stats = ['synced' => 0, 'failed' => 0, 'skipped' => 0];

        Customer::with('package')
            ->whereNotNull('pppoe_username')
            ->where('pppoe_username', '!=', '')
            ->whereIn('status', ['active', 'isolated'])
            ->chunk(100, function ($customers) use (&$stats) {
                foreach ($customers as $customer) {
                    if ($customer->status === 'isolated') {
                        // Sync as isolated
                        $synced = $this->syncCustomer($customer);
                        if ($synced) {
                            $this->isolateCustomer($customer);
                            $stats['synced']++;
                        } else {
                            $stats['failed']++;
                        }
                    } else {
                        $result = $this->syncCustomer($customer);
                        $result ? $stats['synced']++ : $stats['failed']++;
                    }
                }
            });

        Log::info('RADIUS: Bulk sync completed', $stats);
        return $stats;
    }

    /**
     * Sync all routers that have a radius_server_id to NAS table.
     */
    public function syncAllNas(): array
    {
        if (!$this->isEnabled()) {
            return ['synced' => 0, 'failed' => 0];
        }

        $stats = ['synced' => 0, 'failed' => 0];

        Router::whereNotNull('radius_server_id')
            ->with('radiusServer')
            ->where('is_active', true)
            ->each(function (Router $router) use (&$stats) {
                $result = $this->syncNas($router);
                $result ? $stats['synced']++ : $stats['failed']++;
            });

        Log::info('RADIUS: NAS bulk sync completed', $stats);
        return $stats;
    }

    /**
     * Remove old username entries when PPPoE username changes.
     */
    public function removeByUsername(string $username): bool
    {
        if (!$this->isEnabled() || empty($username)) {
            return false;
        }

        try {
            DB::connection('radius')->transaction(function () use ($username) {
                RadCheck::forUser($username)->delete();
                RadReply::forUser($username)->delete();
                RadUserGroup::forUser($username)->delete();
            });

            return true;
        } catch (\Exception $e) {
            Log::error('RADIUS: Failed to remove by username', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
