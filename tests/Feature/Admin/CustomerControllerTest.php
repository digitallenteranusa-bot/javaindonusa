<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->actingAsAdmin();
    }

    public function test_customer_index_page_loads(): void
    {
        Customer::factory()->count(3)->create();

        $response = $this->get(route('admin.customers.index'));

        $response->assertStatus(200);
    }

    public function test_customer_index_with_search_filter(): void
    {
        Customer::factory()->create(['name' => 'Ahmad Sudirman']);
        Customer::factory()->create(['name' => 'Budi Setiawan']);

        $response = $this->get(route('admin.customers.index', ['search' => 'Ahmad']));

        $response->assertStatus(200);
    }

    public function test_customer_store(): void
    {
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        $router = Router::factory()->create();

        $response = $this->post(route('admin.customers.store'), [
            'name' => 'Test Customer',
            'address' => 'Jl. Testing 123',
            'phone' => '081234567890',
            'package_id' => $package->id,
            'area_id' => $area->id,
            'router_id' => $router->id,
            'connection_type' => 'pppoe',
            'billing_type' => 'postpaid',
            'discount_type' => 'none',
            'is_taxed' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customers', ['name' => 'Test Customer']);
    }

    public function test_customer_show(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->get(route('admin.customers.show', $customer));

        $response->assertStatus(200);
    }

    public function test_customer_update(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->put(route('admin.customers.update', $customer), [
            'name' => 'Updated Name',
            'address' => $customer->address,
            'phone' => $customer->phone,
            'package_id' => $customer->package_id,
            'area_id' => $customer->area_id ?? Area::factory()->create()->id,
            'router_id' => $customer->router_id ?? Router::factory()->create()->id,
            'connection_type' => $customer->connection_type,
            'billing_type' => $customer->billing_type ?? 'postpaid',
            'discount_type' => $customer->discount_type ?? 'none',
            'status' => 'active',
            'is_taxed' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Updated Name']);
    }

    public function test_customer_destroy_without_unpaid_invoices(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->delete(route('admin.customers.destroy', $customer));

        $response->assertRedirect();
    }

    public function test_customer_adjust_debt(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 100000]);

        $response = $this->post(route('admin.customers.adjust-debt', $customer), [
            'amount' => 50000,
            'reason' => 'Koreksi tagihan',
        ]);

        $response->assertRedirect();
    }

    public function test_customer_store_validation_fails_without_required_fields(): void
    {
        $response = $this->post(route('admin.customers.store'), []);

        $response->assertSessionHasErrors(['name', 'address', 'phone', 'package_id']);
    }
}
