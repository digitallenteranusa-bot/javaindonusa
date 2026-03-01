<?php

namespace App\Console\Commands;

use App\Models\Radius\Nas;
use App\Models\Radius\RadAcct;
use App\Models\Radius\RadCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RadiusStatus extends Command
{
    protected $signature = 'radius:status';

    protected $description = 'Tampilkan status koneksi dan statistik FreeRADIUS DB';

    public function handle(): int
    {
        if (!config('radius.enabled')) {
            $this->error('RADIUS integration is disabled. Set RADIUS_ENABLED=true in .env');
            return self::FAILURE;
        }

        $this->info('FreeRADIUS Database Status');
        $this->info('==========================');
        $this->newLine();

        // Test connection
        try {
            DB::connection('radius')->getPdo();
            $this->info('Connection: OK');

            $dbName = DB::connection('radius')->getDatabaseName();
            $this->info("Database: {$dbName}");
        } catch (\Exception $e) {
            $this->error('Connection: FAILED - ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();

        // Stats
        try {
            $stats = [
                ['Metric', 'Count'],
                ['Users (radcheck)', RadCheck::distinct('username')->count('username')],
                ['NAS entries', Nas::count()],
                ['Active sessions', RadAcct::active()->count()],
                ['Total sessions (radacct)', RadAcct::count()],
            ];

            $this->table($stats[0], array_slice($stats, 1));
        } catch (\Exception $e) {
            $this->warn('Could not fetch stats: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('Config:');
        $this->info('  Isolation method: ' . config('radius.isolation_method'));
        $this->info('  Isolation rate limit: ' . config('radius.isolation_rate_limit'));
        $this->info('  Default group: ' . config('radius.default_group'));

        return self::SUCCESS;
    }
}
