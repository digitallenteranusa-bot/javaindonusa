<?php

namespace App\Console\Commands;

use App\Services\GenieAcs\GenieAcsService;
use App\Models\CustomerDevice;
use Illuminate\Console\Command;

class GenieAcsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'genieacs:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check GenieACS connection status and device statistics';

    /**
     * Execute the console command.
     */
    public function handle(GenieAcsService $genieAcs): int
    {
        $this->info('GenieACS Status Check');
        $this->info('======================');
        $this->newLine();

        // Connection check
        $this->info('Checking connection...');
        $connection = $genieAcs->checkConnection();

        if ($connection['success']) {
            $this->info('Status: <fg=green>Connected</>');
        } else {
            $this->error('Status: Disconnected');
            $this->error('Error: ' . $connection['message']);
            return Command::FAILURE;
        }

        $this->newLine();

        // GenieACS device count
        $this->info('Fetching device count from GenieACS...');
        $devices = $genieAcs->getDevices();
        $genieCount = $devices ? count($devices) : 0;

        $this->newLine();

        // Local database stats
        $localTotal = CustomerDevice::count();
        $localOnline = CustomerDevice::online()->count();
        $localOffline = CustomerDevice::offline()->count();
        $localUnmatched = CustomerDevice::whereNull('customer_id')->count();

        // Display stats
        $this->info('Device Statistics');
        $this->info('-----------------');

        $this->table(
            ['Source', 'Count'],
            [
                ['GenieACS Devices', $genieCount],
                ['Local Database Total', $localTotal],
                ['Online Devices', $localOnline],
                ['Offline Devices', $localOffline],
                ['Unmatched Devices', $localUnmatched],
            ]
        );

        $this->newLine();

        // Manufacturer breakdown
        $manufacturers = CustomerDevice::selectRaw('manufacturer, COUNT(*) as count')
            ->whereNotNull('manufacturer')
            ->groupBy('manufacturer')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        if ($manufacturers->isNotEmpty()) {
            $this->info('Top Manufacturers');
            $this->info('-----------------');

            $this->table(
                ['Manufacturer', 'Device Count'],
                $manufacturers->map(fn($m) => [$m->manufacturer, $m->count])->toArray()
            );
        }

        $this->newLine();

        // Devices with weak signal
        $weakSignal = CustomerDevice::withLowSignal()->count();
        if ($weakSignal > 0) {
            $this->warn("Devices with weak signal: {$weakSignal}");
        }

        // Configuration info
        $this->newLine();
        $this->info('Configuration');
        $this->info('-------------');
        $this->line('NBI URL: ' . config('genieacs.nbi_url'));
        $this->line('Sync Interval: ' . config('genieacs.sync.interval') . ' minutes');
        $this->line('Offline Threshold: ' . config('genieacs.thresholds.offline_minutes') . ' minutes');

        return Command::SUCCESS;
    }
}
