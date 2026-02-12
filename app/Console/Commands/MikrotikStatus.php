<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Console\Command;

class MikrotikStatus extends Command
{
    protected $signature = 'mikrotik:status
                            {router? : Router ID or name (optional, shows all if not specified)}';

    protected $description = 'Check Mikrotik router status and connection';

    public function handle(MikrotikService $mikrotikService): int
    {
        $routerQuery = $this->argument('router');

        if ($routerQuery) {
            // Single router
            $router = is_numeric($routerQuery)
                ? Router::find($routerQuery)
                : Router::where('name', $routerQuery)->first();

            if (!$router) {
                $this->error("Router not found: {$routerQuery}");
                return Command::FAILURE;
            }

            return $this->checkSingleRouter($mikrotikService, $router);
        }

        // All routers
        $routers = Router::where('is_active', true)->get();

        if ($routers->isEmpty()) {
            $this->warn('No active routers found');
            return Command::SUCCESS;
        }

        $this->info("Checking {$routers->count()} router(s)...");
        $this->newLine();

        $results = [];
        foreach ($routers as $router) {
            $results[] = $this->checkRouterStatus($mikrotikService, $router);
        }

        $this->table(
            ['Router', 'IP', 'Status', 'Version', 'Uptime', 'CPU', 'Memory'],
            $results
        );

        return Command::SUCCESS;
    }

    protected function checkSingleRouter(MikrotikService $mikrotikService, Router $router): int
    {
        $this->info("Checking router: {$router->name}");
        $this->info("IP: {$router->ip_address}:{$router->api_port}");
        $this->newLine();

        try {
            $this->info('Connecting...');
            $mikrotikService->connect($router);
            $this->info('✓ Connected successfully');
            $this->newLine();

            $info = $mikrotikService->getRouterInfo();

            $this->table(['Property', 'Value'], [
                ['Identity', $info['identity']],
                ['Version', $info['version']],
                ['Uptime', $info['uptime']],
                ['Model', $info['model']],
                ['Serial', $info['serial']],
                ['CPU Load', $info['cpu_load'] . '%'],
                ['Memory', $this->formatBytes($info['total_memory'] - $info['free_memory']) . ' / ' . $this->formatBytes($info['total_memory'])],
                ['Storage', $this->formatBytes($info['total_hdd'] - $info['free_hdd']) . ' / ' . $this->formatBytes($info['total_hdd'])],
                ['Architecture', $info['architecture']],
            ]);

            // Get active connections
            $this->newLine();
            $active = $mikrotikService->getActiveConnections();
            $this->info("Active PPPoE connections: " . count($active));

            // Update router status in DB
            $mikrotikService->updateRouterStatus($router);
            $this->info('✓ Router status updated in database');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Connection failed: ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            $mikrotikService->disconnect();
        }
    }

    protected function checkRouterStatus(MikrotikService $mikrotikService, Router $router): array
    {
        try {
            $mikrotikService->connect($router);
            $info = $mikrotikService->getRouterInfo();
            $mikrotikService->disconnect();

            $memoryUsage = $info['total_memory'] > 0
                ? round((($info['total_memory'] - $info['free_memory']) / $info['total_memory']) * 100)
                : 0;

            // Update status di database
            $router->update([
                'identity' => $info['identity'],
                'version' => $info['version'],
                'uptime' => $info['uptime'],
                'cpu_load' => $info['cpu_load'],
                'memory_usage' => $memoryUsage,
                'model' => $info['model'],
                'serial_number' => $info['serial'],
                'last_connected_at' => now(),
            ]);

            return [
                $router->name,
                $router->ip_address,
                '✓ Online',
                $info['version'],
                $info['uptime'],
                $info['cpu_load'] . '%',
                $memoryUsage . '%',
            ];
        } catch (\Exception $e) {
            return [
                $router->name,
                $router->ip_address,
                '✗ Offline',
                '-',
                '-',
                '-',
                '-',
            ];
        }
    }

    protected function formatBytes($bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
