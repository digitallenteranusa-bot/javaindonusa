<?php

namespace App\Observers;

use App\Models\Router;
use App\Services\Radius\RadiusService;
use Illuminate\Support\Facades\Log;

class RouterObserver
{
    /**
     * When router is created with a radius_server_id, sync to NAS.
     */
    public function created(Router $router): void
    {
        if ($router->radius_server_id) {
            $this->syncNas($router);
        }
    }

    /**
     * When radius_server_id or ip_address changes, sync/remove NAS.
     */
    public function updated(Router $router): void
    {
        if ($router->isDirty(['radius_server_id', 'ip_address', 'identity', 'is_active'])) {
            // If radius_server_id was removed, remove from NAS
            $oldRadiusServerId = $router->getOriginal('radius_server_id');
            if ($oldRadiusServerId && !$router->radius_server_id) {
                $this->removeNas($router, $router->getOriginal('ip_address') ?? $router->ip_address);
                return;
            }

            // If IP changed, remove old NAS entry first
            if ($router->isDirty('ip_address') && $oldRadiusServerId) {
                $this->removeNas($router, $router->getOriginal('ip_address'));
            }

            // Sync current state
            if ($router->radius_server_id) {
                $this->syncNas($router);
            }
        }
    }

    /**
     * When router is deleted, remove from NAS.
     */
    public function deleted(Router $router): void
    {
        if ($router->radius_server_id) {
            $this->removeNas($router, $router->ip_address);
        }
    }

    private function syncNas(Router $router): void
    {
        try {
            app(RadiusService::class)->syncNas($router);
        } catch (\Exception $e) {
            Log::warning('RADIUS NAS sync failed on router change', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function removeNas(Router $router, string $ipAddress): void
    {
        try {
            $radiusService = app(RadiusService::class);
            if ($radiusService->isEnabled()) {
                \App\Models\Radius\Nas::where('nasname', $ipAddress)->delete();
                Log::info('RADIUS: NAS removed via observer', ['nasname' => $ipAddress]);
            }
        } catch (\Exception $e) {
            Log::warning('RADIUS NAS removal failed on router change', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
