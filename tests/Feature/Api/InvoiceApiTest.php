<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_can_list_invoices(): void
    {
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        $customer = Customer::factory()->create([
            'package_id' => $package->id,
            'area_id' => $area->id,
        ]);
        Invoice::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/invoices');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'invoice_number', 'total_amount', 'status']],
            ]);
    }

    public function test_can_filter_invoices_by_status(): void
    {
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        $customer = Customer::factory()->create([
            'package_id' => $package->id,
            'area_id' => $area->id,
        ]);
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'paid',
        ]);
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/invoices?status=paid');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_show_invoice_detail(): void
    {
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        $customer = Customer::factory()->create([
            'package_id' => $package->id,
            'area_id' => $area->id,
        ]);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
            ]);
    }

    public function test_invoice_resource_hides_sensitive_fields(): void
    {
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        $customer = Customer::factory()->create([
            'package_id' => $package->id,
            'area_id' => $area->id,
        ]);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'notes' => 'internal note',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonMissing(['notes' => 'internal note']);
    }
}
