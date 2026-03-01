<?php

namespace App\Console\Commands;

use App\Services\Radius\RadiusService;
use Illuminate\Console\Command;

class RadiusSync extends Command
{
    protected $signature = 'radius:sync
        {--customers : Sync semua customer ke RADIUS DB}
        {--nas : Sync semua router ke NAS table}
        {--all : Sync customers dan NAS}
        {--dry-run : Tampilkan apa yang akan dilakukan tanpa eksekusi}';

    protected $description = 'Sync data ke FreeRADIUS database';

    public function handle(RadiusService $radiusService): int
    {
        if (!$radiusService->isEnabled()) {
            $this->error('RADIUS integration is disabled. Set RADIUS_ENABLED=true in .env');
            return self::FAILURE;
        }

        $syncCustomers = $this->option('customers') || $this->option('all');
        $syncNas = $this->option('nas') || $this->option('all');

        if (!$syncCustomers && !$syncNas) {
            $this->error('Specify --customers, --nas, or --all');
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] No changes will be made.');
        }

        if ($syncCustomers) {
            $this->info('Syncing customers to RADIUS DB...');

            if ($this->option('dry-run')) {
                $count = \App\Models\Customer::whereNotNull('pppoe_username')
                    ->where('pppoe_username', '!=', '')
                    ->whereIn('status', ['active', 'isolated'])
                    ->count();
                $this->info("  Would sync {$count} customers.");
            } else {
                $stats = $radiusService->syncAllCustomers();
                $this->info("  Synced: {$stats['synced']}");
                $this->info("  Failed: {$stats['failed']}");
                $this->info("  Skipped: {$stats['skipped']}");
            }
        }

        if ($syncNas) {
            $this->info('Syncing routers to NAS table...');

            if ($this->option('dry-run')) {
                $count = \App\Models\Router::whereNotNull('radius_server_id')
                    ->where('is_active', true)
                    ->count();
                $this->info("  Would sync {$count} routers.");
            } else {
                $stats = $radiusService->syncAllNas();
                $this->info("  Synced: {$stats['synced']}");
                $this->info("  Failed: {$stats['failed']}");
            }
        }

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
