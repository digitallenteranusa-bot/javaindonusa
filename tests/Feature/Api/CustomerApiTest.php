<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->collector = User::factory()->collector()->create();
    }

    public function test_admin_can_list_all_customers(): void
    {
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        Customer::factory()->count(3)->create([
            'package_id' => $package->id,
            'area_id' => $area->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/customers');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'customer_id', 'name', 'phone', 'status', 'total_debt']],
                'meta',
                'links',
            ]);
    }

    public function test_collector_only_sees_assigned_customers(): void
    {
        $otherCollector = User::factory()->collector()->create();
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        Customer::factory()->count(2)->create([
            'collector_id' => $this->collector->id,
            'package_id' => $package->id,
            'area_id' => $area->id,
        ]);
        Customer::factory()->create([
            'collector_id' => $otherCollector->id,
            'package_id' => $package->id,
            'area_id' => $area->id,
        ]); // assigned to different collector

        $response = $this->actingAs($this->collector, 'sanctum')
            ->getJson('/api/v1/customers');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_customers_by_status(): void
    {
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        Customer::factory()->create([
            'status' => 'active',
            'package_id' => $package->id,
            'area_id' => $area->id,
        ]);
        Customer::factory()->create([
            'status' => 'isolated',
            'package_id' => $package->id,
            'area_id' => $area->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/customers?status=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_show_customer_detail(): void
    {
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        $customer = Customer::factory()->create([
            'package_id' => $package->id,
            'area_id' => $area->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $customer->id,
                    'customer_id' => $customer->customer_id,
                    'name' => $customer->name,
                ],
            ]);
    }

    public function test_customer_resource_hides_sensitive_fields(): void
    {
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        $customer = Customer::factory()->create([
            'package_id' => $package->id,
            'area_id' => $area->id,
            'pppoe_username' => 'test_pppoe',
            'nik' => '1234567890123456',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertOk()
            ->assertJsonMissing(['pppoe_username' => 'test_pppoe'])
            ->assertJsonMissing(['nik' => '1234567890123456']);
    }
}
