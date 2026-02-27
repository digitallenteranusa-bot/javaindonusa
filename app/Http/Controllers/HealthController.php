<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __invoke()
    {
        $checks = [];
        $healthy = true;

        // Database
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'fail', 'message' => $e->getMessage()];
            $healthy = false;
        }

        // Redis
        try {
            Redis::ping();
            $checks['redis'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['redis'] = ['status' => 'fail', 'message' => $e->getMessage()];
            $healthy = false;
        }

        // Queue worker (check if jobs are being processed via cache heartbeat)
        try {
            $lastHeartbeat = Cache::get('queue:worker:heartbeat');
            if ($lastHeartbeat && now()->diffInMinutes($lastHeartbeat) < 10) {
                $checks['queue'] = ['status' => 'ok', 'last_heartbeat' => $lastHeartbeat];
            } else {
                $checks['queue'] = ['status' => 'warn', 'message' => 'No recent worker heartbeat'];
            }
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'warn', 'message' => $e->getMessage()];
        }

        // Disk space
        try {
            $storagePath = storage_path();
            $freeBytes = disk_free_space($storagePath);
            $totalBytes = disk_total_space($storagePath);
            $usedPercent = round((1 - $freeBytes / $totalBytes) * 100, 1);

            $checks['disk'] = [
                'status' => $usedPercent > 90 ? 'warn' : 'ok',
                'used_percent' => $usedPercent,
                'free_mb' => round($freeBytes / 1024 / 1024),
            ];

            if ($usedPercent > 95) {
                $healthy = false;
                $checks['disk']['status'] = 'fail';
            }
        } catch (\Throwable $e) {
            $checks['disk'] = ['status' => 'warn', 'message' => $e->getMessage()];
        }

        // Mikrotik (from cache, non-blocking)
        try {
            $mikrotikStatus = Cache::get('mikrotik:last_status');
            $checks['mikrotik'] = $mikrotikStatus
                ? ['status' => 'ok', 'last_check' => $mikrotikStatus]
                : ['status' => 'unknown', 'message' => 'No cached status'];
        } catch (\Throwable $e) {
            $checks['mikrotik'] = ['status' => 'unknown', 'message' => $e->getMessage()];
        }

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
