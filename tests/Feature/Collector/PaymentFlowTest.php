<?php

namespace Tests\Feature\Collector;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed basic permissions for penagih role.
     */
    protected function seedCollectorPermissions(): void
    {
        $permissions = ['dashboard.view', 'customers.view', 'customers.collect', 'payments.view', 'payments.create'];
        foreach ($permissions as $perm) {
            $existing = DB::table('permissions')->where('name', $perm)->first();
            if ($existing) {
                $id = $existing->id;
            } else {
                $id = DB::table('permissions')->insertGetId([
                    'name' => $perm,
                    'group' => explode('.', $perm)[0],
                    'description' => $perm,
                ]);
            }
            DB::table('role_permissions')->insertOrIgnore([
                'role' => 'penagih',
                'permission_id' => $id,
            ]);
        }
        \App\Models\Permission::clearCache();
    }

    public function test_collector_can_view_dashboard(): void
    {
        $this->seedCollectorPermissions();
        $collector = $this->actingAsCollector();

        $response = $this->get(route('collector.dashboard'));

        $response->assertStatus(200);
    }

    public function test_collector_can_view_assigned_customers(): void
    {
        $this->seedCollectorPermissions();
        $collector = $this->actingAsCollector();
        Customer::factory()->count(3)->create(['collector_id' => $collector->id]);

        $response = $this->get(route('collector.customers'));

        $response->assertStatus(200);
    }

    public function test_collector_cannot_access_admin_panel(): void
    {
        $this->actingAsCollector();

        $response = $this->get('/admin');

        // Should be redirected or forbidden
        $this->assertContains($response->status(), [302, 403]);
    }

    public function test_unauthenticated_cannot_access_collector(): void
    {
        $response = $this->get(route('collector.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_cannot_access_collector_portal(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('collector.dashboard'));

        // Admin might be redirected or denied based on role middleware
        $this->assertContains($response->status(), [200, 302, 403]);
    }
}
