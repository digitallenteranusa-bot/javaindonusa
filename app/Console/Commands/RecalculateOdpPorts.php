<?php

namespace App\Console\Commands;

use App\Models\Odp;
use Illuminate\Console\Command;

class RecalculateOdpPorts extends Command
{
    protected $signature = 'odp:recalculate-ports';
    protected $description = 'Recalculate used_ports for all ODPs based on actual customer count';

    public function handle()
    {
        $odps = Odp::all();
        $updated = 0;

        foreach ($odps as $odp) {
            $actualCount = $odp->customers()->count();
            if ($odp->used_ports !== $actualCount) {
                $this->line("ODP {$odp->code} ({$odp->name}): {$odp->used_ports} → {$actualCount}");
                $odp->update(['used_ports' => $actualCount]);
                $updated++;
            }
        }

        $this->info("Selesai. {$updated} ODP diperbarui dari total {$odps->count()} ODP.");

        return Command::SUCCESS;
    }
}
