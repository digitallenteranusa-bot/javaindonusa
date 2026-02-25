<?php

namespace App\Services\Admin;

use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Router;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NetworkMonitoringService
{
    public function getNetworkHealthData(): array
    {
        $alerts = $this->getNetworkAlerts();

        return [
            'overview' => $this->getOverview(count($alerts)),
            'routerHealth' => $this->getRouterHealth(),
            'deviceHealth' => $this->getDeviceHealth(),
            'signalDistribution' => $this->getSignalDistribution(),
            'alerts' => $alerts,
            'routerCpuSnapshot' => $this->getRouterCpuSnapshot(),
            'connectionStats' => $this->getConnectionStats(),
            'lastUpdated' => now()->format('Y-m-d H:i:s'),
        ];
    }

    public function getOverview(?int $alertCount = null): array
    {
        $onlineThreshold = Carbon::now()->subMinutes(10);

        $routers = Router::active()->get();
        $routerOnline = $routers->filter(fn ($r) => $r->last_connected_at && $r->last_connected_at->gte($onlineThreshold))->count();
        $routerOffline = $routers->count() - $routerOnline;

        $deviceOnline = CustomerDevice::online()->count();
        $deviceOffline = CustomerDevice::offline()->count();
        $weakSignal = CustomerDevice::withLowSignal()->count();

        $customerActive = Customer::where('status', Customer::STATUS_ACTIVE)->count();
        $customerIsolated = Customer::where('status', Customer::STATUS_ISOLATED)->count();

        return [
            'router_online' => $routerOnline,
            'router_offline' => $routerOffline,
            'router_total' => $routers->count(),
            'device_online' => $deviceOnline,
            'device_offline' => $deviceOffline,
            'device_total' => $deviceOnline + $deviceOffline,
            'weak_signal' => $weakSignal,
            'customer_active' => $customerActive,
            'customer_isolated' => $customerIsolated,
            'alert_count' => $alertCount ?? count($this->getNetworkAlerts()),
        ];
    }

    public function getRouterHealth(): array
    {
        $onlineThreshold = Carbon::now()->subMinutes(10);

        return Router::active()
            ->withCount(['customers'])
            ->get()
            ->map(function ($router) use ($onlineThreshold) {
                $isOnline = $router->last_connected_at && $router->last_connected_at->gte($onlineThreshold);

                return [
                    'id' => $router->id,
                    'name' => $router->name,
                    'ip_address' => $router->ip_address,
                    'is_online' => $isOnline,
                    'cpu_load' => $router->cpu_load ?? 0,
                    'memory_usage' => $router->memory_usage ?? 0,
                    'uptime' => $router->uptime,
                    'version' => $router->version,
                    'customer_count' => $router->customers_count,
                    'last_connected_at' => $router->last_connected_at?->format('Y-m-d H:i:s'),
                ];
            })
            ->sortByDesc('is_online')
            ->values()
            ->toArray();
    }

    public function getDeviceHealth(): array
    {
        $online = CustomerDevice::online()->count();
        $offline = CustomerDevice::offline()->count();

        $byManufacturer = CustomerDevice::select('manufacturer', DB::raw('SUM(is_online = 1) as online_count'), DB::raw('SUM(is_online = 0) as offline_count'))
            ->whereNotNull('manufacturer')
            ->where('manufacturer', '!=', '')
            ->groupBy('manufacturer')
            ->orderByDesc(DB::raw('SUM(is_online = 1) + SUM(is_online = 0)'))
            ->limit(10)
            ->get()
            ->map(fn ($d) => [
                'manufacturer' => $d->manufacturer,
                'online' => (int) $d->online_count,
                'offline' => (int) $d->offline_count,
            ])
            ->toArray();

        return [
            'online' => $online,
            'offline' => $offline,
            'total' => $online + $offline,
            'by_manufacturer' => $byManufacturer,
        ];
    }

    public function getSignalDistribution(): array
    {
        $minPower = config('genieacs.thresholds.rx_power_min', -28);
        $maxPower = config('genieacs.thresholds.rx_power_max', -8);

        $good = CustomerDevice::whereNotNull('rx_power')
            ->where('rx_power', '>=', $minPower)
            ->where('rx_power', '<=', $maxPower)
            ->count();

        $weak = CustomerDevice::whereNotNull('rx_power')
            ->where('rx_power', '<', $minPower)
            ->count();

        $tooStrong = CustomerDevice::whereNotNull('rx_power')
            ->where('rx_power', '>', $maxPower)
            ->count();

        $unknown = CustomerDevice::whereNull('rx_power')->count();

        return [
            ['label' => 'Baik', 'value' => $good, 'color' => '#10B981'],
            ['label' => 'Lemah', 'value' => $weak, 'color' => '#EF4444'],
            ['label' => 'Terlalu Kuat', 'value' => $tooStrong, 'color' => '#F59E0B'],
            ['label' => 'Tidak Diketahui', 'value' => $unknown, 'color' => '#9CA3AF'],
        ];
    }

    public function getNetworkAlerts(): array
    {
        $alerts = [];
        $onlineThreshold = Carbon::now()->subMinutes(10);

        // Router offline alerts
        $offlineRouters = Router::active()
            ->where(function ($q) use ($onlineThreshold) {
                $q->whereNull('last_connected_at')
                    ->orWhere('last_connected_at', '<', $onlineThreshold);
            })
            ->get();

        foreach ($offlineRouters as $router) {
            $alerts[] = [
                'type' => 'danger',
                'category' => 'Router',
                'title' => "Router Offline: {$router->name}",
                'message' => "Router {$router->name} ({$router->ip_address}) tidak merespons sejak " . ($router->last_connected_at?->format('d/m/Y H:i') ?? 'tidak diketahui'),
                'link' => "/admin/routers/{$router->id}",
            ];
        }

        // High CPU alerts
        $highCpuRouters = Router::active()
            ->where('cpu_load', '>', 80)
            ->get();

        foreach ($highCpuRouters as $router) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'Router',
                'title' => "CPU Tinggi: {$router->name}",
                'message' => "CPU load {$router->cpu_load}% pada router {$router->name}",
                'link' => "/admin/routers/{$router->id}",
            ];
        }

        // High memory alerts
        $highMemRouters = Router::active()
            ->where('memory_usage', '>', 80)
            ->get();

        foreach ($highMemRouters as $router) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'Router',
                'title' => "Memori Tinggi: {$router->name}",
                'message' => "Memory usage {$router->memory_usage}% pada router {$router->name}",
                'link' => "/admin/routers/{$router->id}",
            ];
        }

        // Weak signal alerts (top 10)
        $weakDevices = CustomerDevice::withLowSignal()
            ->with('customer:id,name,customer_id')
            ->orderBy('rx_power')
            ->limit(10)
            ->get();

        foreach ($weakDevices as $device) {
            $customerName = $device->customer?->name ?? 'Unknown';
            $alerts[] = [
                'type' => 'warning',
                'category' => 'Sinyal',
                'title' => "Sinyal Lemah: {$customerName}",
                'message' => "RX Power {$device->rx_power} dBm pada device {$device->serial_number}",
                'link' => $device->customer ? "/admin/customers/{$device->customer->id}" : '#',
            ];
        }

        // Sort: danger first, then warning
        usort($alerts, fn ($a, $b) => ($a['type'] === 'danger' ? 0 : 1) - ($b['type'] === 'danger' ? 0 : 1));

        return $alerts;
    }

    public function getRouterCpuSnapshot(): array
    {
        return Router::active()
            ->whereNotNull('cpu_load')
            ->orderByDesc('cpu_load')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'cpu_load' => $r->cpu_load ?? 0,
                'memory_usage' => $r->memory_usage ?? 0,
            ])
            ->toArray();
    }

    public function getConnectionStats(): array
    {
        return Customer::select(
            'connection_type',
            'status',
            DB::raw('COUNT(*) as total')
        )
            ->whereNotNull('connection_type')
            ->where('connection_type', '!=', '')
            ->whereIn('status', [Customer::STATUS_ACTIVE, Customer::STATUS_ISOLATED])
            ->groupBy('connection_type', 'status')
            ->get()
            ->groupBy('connection_type')
            ->map(function ($group, $type) {
                return [
                    'type' => $type,
                    'active' => $group->where('status', Customer::STATUS_ACTIVE)->sum('total'),
                    'isolated' => $group->where('status', Customer::STATUS_ISOLATED)->sum('total'),
                ];
            })
            ->values()
            ->toArray();
    }
}
