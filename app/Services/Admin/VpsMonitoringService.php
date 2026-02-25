<?php

namespace App\Services\Admin;

class VpsMonitoringService
{
    public function getVpsHealthData(): array
    {
        $alerts = $this->getAlerts();

        return [
            'overview' => $this->getOverview($alerts),
            'systemInfo' => $this->getSystemOverview(),
            'cpuUsage' => $this->getCpuUsage(),
            'memoryUsage' => $this->getMemoryUsage(),
            'diskUsage' => $this->getDiskUsage(),
            'networkStats' => $this->getNetworkStats(),
            'activeConnections' => $this->getActiveConnections(),
            'serviceStatuses' => $this->getServiceStatuses(),
            'topProcesses' => $this->getTopProcesses(),
            'alerts' => $alerts,
            'lastUpdated' => now()->format('Y-m-d H:i:s'),
        ];
    }

    protected function getOverview(array $alerts): array
    {
        $cpu = $this->getCpuUsage();
        $memory = $this->getMemoryUsage();
        $disk = $this->getDiskUsage();
        $systemInfo = $this->getSystemOverview();

        $maxDiskPercent = 0;
        foreach ($disk as $partition) {
            if (($partition['use_percent'] ?? 0) > $maxDiskPercent) {
                $maxDiskPercent = $partition['use_percent'];
            }
        }

        return [
            'cpu_percent' => $cpu['usage_percent'] ?? 0,
            'ram_percent' => $memory['used_percent'] ?? 0,
            'disk_percent' => $maxDiskPercent,
            'load_average' => $systemInfo['load_average'] ?? 'N/A',
            'uptime' => $systemInfo['uptime'] ?? 'N/A',
            'alert_count' => count($alerts),
        ];
    }

    public function getSystemOverview(): array
    {
        try {
            $hostname = trim(shell_exec('hostname') ?? 'N/A');
            $os = trim(shell_exec('cat /etc/os-release 2>/dev/null | grep PRETTY_NAME | cut -d= -f2 | tr -d \'"\'') ?? 'N/A');
            if (empty($os)) {
                $os = trim(shell_exec('uname -o') ?? 'N/A');
            }
            $kernel = trim(shell_exec('uname -r') ?? 'N/A');
            $uptime = trim(shell_exec('uptime -p') ?? 'N/A');
            $loadAvg = trim(shell_exec('cat /proc/loadavg 2>/dev/null | awk \'{print $1", "$2", "$3}\'') ?? 'N/A');
            $phpVersion = PHP_VERSION;
            $laravelVersion = app()->version();

            return [
                'hostname' => $hostname,
                'os' => $os ?: 'N/A',
                'kernel' => $kernel,
                'uptime' => $uptime,
                'load_average' => $loadAvg,
                'php_version' => $phpVersion,
                'laravel_version' => $laravelVersion,
            ];
        } catch (\Exception $e) {
            return [
                'hostname' => 'N/A',
                'os' => 'N/A',
                'kernel' => 'N/A',
                'uptime' => 'N/A',
                'load_average' => 'N/A',
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ];
        }
    }

    public function getCpuUsage(): array
    {
        try {
            // Try mpstat first, fallback to top
            $output = shell_exec('mpstat 1 1 2>/dev/null | tail -1 | awk \'{print $NF}\'');
            if ($output && is_numeric(trim($output))) {
                $idle = (float) trim($output);
                $usage = round(100 - $idle, 1);
            } else {
                $output = shell_exec('top -bn1 2>/dev/null | grep "Cpu(s)" | awk \'{print $2}\'');
                $usage = $output ? round((float) trim($output), 1) : 0;
            }

            $cores = trim(shell_exec('nproc 2>/dev/null') ?? '1');

            return [
                'usage_percent' => $usage,
                'cores' => (int) $cores,
            ];
        } catch (\Exception $e) {
            return ['usage_percent' => 0, 'cores' => 1];
        }
    }

    public function getMemoryUsage(): array
    {
        try {
            $output = shell_exec('free -b 2>/dev/null');
            if (!$output) {
                return $this->getDefaultMemory();
            }

            $lines = explode("\n", trim($output));
            if (count($lines) < 2) {
                return $this->getDefaultMemory();
            }

            // Parse Mem line
            $memParts = preg_split('/\s+/', trim($lines[1]));
            $total = (int) ($memParts[1] ?? 0);
            $used = (int) ($memParts[2] ?? 0);
            $free = (int) ($memParts[3] ?? 0);
            $cached = (int) ($memParts[5] ?? 0);

            // Parse Swap line
            $swapTotal = 0;
            $swapUsed = 0;
            if (isset($lines[2])) {
                $swapParts = preg_split('/\s+/', trim($lines[2]));
                $swapTotal = (int) ($swapParts[1] ?? 0);
                $swapUsed = (int) ($swapParts[2] ?? 0);
            }

            $usedPercent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
            $swapPercent = $swapTotal > 0 ? round(($swapUsed / $swapTotal) * 100, 1) : 0;

            return [
                'total' => $total,
                'used' => $used,
                'free' => $free,
                'cached' => $cached,
                'used_percent' => $usedPercent,
                'swap_total' => $swapTotal,
                'swap_used' => $swapUsed,
                'swap_percent' => $swapPercent,
            ];
        } catch (\Exception $e) {
            return $this->getDefaultMemory();
        }
    }

    protected function getDefaultMemory(): array
    {
        return [
            'total' => 0, 'used' => 0, 'free' => 0, 'cached' => 0,
            'used_percent' => 0, 'swap_total' => 0, 'swap_used' => 0, 'swap_percent' => 0,
        ];
    }

    public function getDiskUsage(): array
    {
        try {
            $output = shell_exec('df -B1 --output=target,size,used,avail,pcent -x tmpfs -x devtmpfs 2>/dev/null');
            if (!$output) {
                return [];
            }

            $lines = explode("\n", trim($output));
            array_shift($lines); // Remove header

            $disks = [];
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 5) {
                    $disks[] = [
                        'mount' => $parts[0],
                        'size' => (int) $parts[1],
                        'used' => (int) $parts[2],
                        'available' => (int) $parts[3],
                        'use_percent' => (int) str_replace('%', '', $parts[4]),
                    ];
                }
            }

            return $disks;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getNetworkStats(): array
    {
        try {
            $output = shell_exec('cat /proc/net/dev 2>/dev/null');
            if (!$output) {
                return [];
            }

            $lines = explode("\n", trim($output));
            // Skip first 2 header lines
            array_shift($lines);
            array_shift($lines);

            $interfaces = [];
            foreach ($lines as $line) {
                $parts = preg_split('/[\s:]+/', trim($line));
                if (count($parts) < 11) {
                    continue;
                }

                $name = $parts[0];
                // Skip loopback
                if ($name === 'lo') {
                    continue;
                }

                $interfaces[] = [
                    'name' => $name,
                    'rx_bytes' => (int) $parts[1],
                    'rx_packets' => (int) $parts[2],
                    'rx_errors' => (int) $parts[3],
                    'tx_bytes' => (int) $parts[9],
                    'tx_packets' => (int) $parts[10],
                    'tx_errors' => (int) $parts[11],
                ];
            }

            return $interfaces;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getActiveConnections(): array
    {
        try {
            $output = shell_exec('ss -s 2>/dev/null');
            if (!$output) {
                return [];
            }

            $connections = [];

            // Parse TCP line for established count
            if (preg_match('/TCP:\s+(\d+)/', $output, $matches)) {
                $connections['tcp_total'] = (int) $matches[1];
            }

            if (preg_match('/estab\s+(\d+)/', $output, $matches)) {
                $connections['established'] = (int) $matches[1];
            }

            if (preg_match('/closed\s+(\d+)/', $output, $matches)) {
                $connections['closed'] = (int) $matches[1];
            }

            if (preg_match('/timewait\s+(\d+)/', $output, $matches)) {
                $connections['time_wait'] = (int) $matches[1];
            }

            // Get per-state breakdown
            $stateOutput = shell_exec('ss -t state established 2>/dev/null | wc -l');
            if ($stateOutput) {
                $connections['established'] = max(0, (int) trim($stateOutput) - 1);
            }

            $timeWaitOutput = shell_exec('ss -t state time-wait 2>/dev/null | wc -l');
            if ($timeWaitOutput) {
                $connections['time_wait'] = max(0, (int) trim($timeWaitOutput) - 1);
            }

            $closeWaitOutput = shell_exec('ss -t state close-wait 2>/dev/null | wc -l');
            if ($closeWaitOutput) {
                $connections['close_wait'] = max(0, (int) trim($closeWaitOutput) - 1);
            }

            return $connections;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getServiceStatuses(): array
    {
        $services = ['nginx', 'mysql', 'redis-server', 'supervisor'];

        // Auto-detect active PHP-FPM version
        $phpFpmService = $this->detectPhpFpm();
        if ($phpFpmService) {
            $services[] = $phpFpmService;
        }

        $statuses = [];
        foreach ($services as $service) {
            try {
                $output = trim(shell_exec("systemctl is-active {$service} 2>/dev/null") ?? 'unknown');
                $statuses[] = [
                    'name' => $service,
                    'status' => $output,
                    'is_active' => $output === 'active',
                ];
            } catch (\Exception $e) {
                $statuses[] = [
                    'name' => $service,
                    'status' => 'unknown',
                    'is_active' => false,
                ];
            }
        }

        // Check queue worker via supervisor (any worker process)
        try {
            $output = shell_exec('supervisorctl status 2>/dev/null');
            $queueRunning = $output && preg_match('/\bRUNNING\b/', $output);
            $statuses[] = [
                'name' => 'queue-worker',
                'status' => $queueRunning ? 'active' : 'inactive',
                'is_active' => (bool) $queueRunning,
            ];
        } catch (\Exception $e) {
            $statuses[] = [
                'name' => 'queue-worker',
                'status' => 'unknown',
                'is_active' => false,
            ];
        }

        return $statuses;
    }

    protected function detectPhpFpm(): ?string
    {
        try {
            // Check common PHP-FPM versions from newest to oldest
            foreach (['8.4', '8.3', '8.2', '8.1', '8.0'] as $version) {
                $output = trim(shell_exec("systemctl is-active php{$version}-fpm 2>/dev/null") ?? '');
                if ($output === 'active') {
                    return "php{$version}-fpm";
                }
            }
            // If none active, return current PHP version's fpm
            $major = PHP_MAJOR_VERSION;
            $minor = PHP_MINOR_VERSION;
            return "php{$major}.{$minor}-fpm";
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getTopProcesses(): array
    {
        try {
            $output = shell_exec('ps aux --sort=-%cpu 2>/dev/null | head -11');
            if (!$output) {
                return [];
            }

            $lines = explode("\n", trim($output));
            array_shift($lines); // Remove header

            $processes = [];
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line), 11);
                if (count($parts) >= 11) {
                    $processes[] = [
                        'user' => $parts[0],
                        'pid' => (int) $parts[1],
                        'cpu' => (float) $parts[2],
                        'mem' => (float) $parts[3],
                        'command' => mb_substr($parts[10], 0, 80),
                    ];
                }
            }

            return $processes;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAlerts(): array
    {
        $alerts = [];

        // CPU alert
        $cpu = $this->getCpuUsage();
        if (($cpu['usage_percent'] ?? 0) > 80) {
            $alerts[] = [
                'type' => 'danger',
                'category' => 'CPU',
                'title' => 'CPU Usage Tinggi',
                'message' => "CPU usage saat ini {$cpu['usage_percent']}% (threshold: 80%)",
            ];
        } elseif (($cpu['usage_percent'] ?? 0) > 60) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'CPU',
                'title' => 'CPU Usage Meningkat',
                'message' => "CPU usage saat ini {$cpu['usage_percent']}%",
            ];
        }

        // Memory alert
        $memory = $this->getMemoryUsage();
        if (($memory['used_percent'] ?? 0) > 80) {
            $alerts[] = [
                'type' => 'danger',
                'category' => 'RAM',
                'title' => 'RAM Usage Tinggi',
                'message' => "RAM usage saat ini {$memory['used_percent']}% (threshold: 80%)",
            ];
        } elseif (($memory['used_percent'] ?? 0) > 60) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'RAM',
                'title' => 'RAM Usage Meningkat',
                'message' => "RAM usage saat ini {$memory['used_percent']}%",
            ];
        }

        // Disk alerts
        $disks = $this->getDiskUsage();
        foreach ($disks as $disk) {
            if (($disk['use_percent'] ?? 0) > 85) {
                $alerts[] = [
                    'type' => 'danger',
                    'category' => 'Disk',
                    'title' => "Disk Hampir Penuh: {$disk['mount']}",
                    'message' => "Disk {$disk['mount']} usage {$disk['use_percent']}% (threshold: 85%)",
                ];
            } elseif (($disk['use_percent'] ?? 0) > 70) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'Disk',
                    'title' => "Disk Usage Tinggi: {$disk['mount']}",
                    'message' => "Disk {$disk['mount']} usage {$disk['use_percent']}%",
                ];
            }
        }

        // Service alerts
        $services = $this->getServiceStatuses();
        foreach ($services as $service) {
            if (!$service['is_active'] && $service['status'] !== 'unknown') {
                $alerts[] = [
                    'type' => 'danger',
                    'category' => 'Service',
                    'title' => "Service Down: {$service['name']}",
                    'message' => "Service {$service['name']} status: {$service['status']}",
                ];
            }
        }

        // Swap alert
        if (($memory['swap_percent'] ?? 0) > 50) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'Swap',
                'title' => 'Swap Usage Tinggi',
                'message' => "Swap usage saat ini {$memory['swap_percent']}%",
            ];
        }

        // Sort: danger first
        usort($alerts, fn ($a, $b) => ($a['type'] === 'danger' ? 0 : 1) - ($b['type'] === 'danger' ? 0 : 1));

        return $alerts;
    }
}
