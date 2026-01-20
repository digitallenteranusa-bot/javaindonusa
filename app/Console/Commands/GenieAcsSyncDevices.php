<?php

namespace App\Console\Commands;

use App\Services\GenieAcs\GenieAcsService;
use Illuminate\Console\Command;

class GenieAcsSyncDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'genieacs:sync-devices
                            {--force : Force sync even if recently synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync devices from GenieACS to local database';

    /**
     * Execute the console command.
     */
    public function handle(GenieAcsService $genieAcs): int
    {
        $this->info('Starting GenieACS device sync...');
        $this->newLine();

        // Check connection first
        $this->info('Checking GenieACS connection...');
        $connection = $genieAcs->checkConnection();

        if (!$connection['success']) {
            $this->error('Failed to connect to GenieACS: ' . $connection['message']);
            return Command::FAILURE;
        }

        $this->info('Connected to GenieACS successfully.');
        $this->newLine();

        // Start sync
        $this->info('Syncing devices...');
        $result = $genieAcs->syncAllDevices();

        if (!$result['success']) {
            $this->error('Sync failed: ' . ($result['message'] ?? 'Unknown error'));
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('========================================');
        $this->info('Sync completed!');
        $this->info('========================================');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Synced', $result['synced']],
                ['Created', $result['created']],
                ['Updated', $result['updated']],
                ['Errors', count($result['errors'])],
            ]
        );

        if (!empty($result['errors'])) {
            $this->newLine();
            $this->warn('Errors during sync:');

            foreach ($result['errors'] as $error) {
                $this->line("  - {$error['device_id']}: {$error['error']}");
            }
        }

        return Command::SUCCESS;
    }
}
