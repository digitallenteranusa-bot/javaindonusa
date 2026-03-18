<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RadiusCleanup extends Command
{
    protected $signature = 'radius:cleanup {--months=3 : Hapus data lebih lama dari X bulan} {--dry-run : Preview tanpa menghapus}';

    protected $description = 'Cleanup data lama di radacct dan radpostauth (hanya session yang sudah selesai)';

    public function handle(): int
    {
        if (!config('radius.enabled')) {
            $this->error('RADIUS integration is disabled.');
            return self::FAILURE;
        }

        $months = (int) $this->option('months');
        $dryRun = $this->option('dry-run');
        $cutoff = now()->subMonths($months)->toDateTimeString();

        $this->info("Cleanup RADIUS data lebih lama dari {$months} bulan (sebelum {$cutoff})");
        if ($dryRun) {
            $this->warn('DRY RUN — tidak ada data yang dihapus');
        }

        $conn = DB::connection('radius');

        // Cleanup radacct — hanya session yang sudah selesai (acctstoptime IS NOT NULL)
        $acctCount = $conn->table('radacct')
            ->whereNotNull('acctstoptime')
            ->where('acctstoptime', '<', $cutoff)
            ->count();

        $this->info("radacct: {$acctCount} record selesai akan dihapus");

        if (!$dryRun && $acctCount > 0) {
            $deleted = $conn->table('radacct')
                ->whereNotNull('acctstoptime')
                ->where('acctstoptime', '<', $cutoff)
                ->delete();
            $this->info("radacct: {$deleted} record dihapus");
        }

        // Cleanup radpostauth
        $postAuthCount = $conn->table('radpostauth')
            ->where('authdate', '<', $cutoff)
            ->count();

        $this->info("radpostauth: {$postAuthCount} record akan dihapus");

        if (!$dryRun && $postAuthCount > 0) {
            $deleted = $conn->table('radpostauth')
                ->where('authdate', '<', $cutoff)
                ->delete();
            $this->info("radpostauth: {$deleted} record dihapus");
        }

        if (!$dryRun) {
            Log::info('RADIUS cleanup completed', [
                'months' => $months,
                'radacct_deleted' => $acctCount,
                'radpostauth_deleted' => $postAuthCount,
            ]);
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
