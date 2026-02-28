<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        $this->customer = Customer::factory()->create([
            'package_id' => $package->id,
            'area_id' => $area->id,
            'total_debt' => 500000,
        ]);
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'package_id' => $package->id,
            'status' => 'pending',
            'total_amount' => 500000,
            'remaining_amount' => 500000,
        ]);
    }

    public function test_can_create_payment(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/payments', [
                'customer_id' => $this->customer->id,
                'amount' => 250000,
                'payment_method' => 'cash',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'payment_number', 'amount', 'payment_method', 'status'],
            ]);
    }

    public function test_payment_requires_valid_data(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/payments', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'amount', 'payment_method']);
    }

    public function test_payment_rejects_invalid_method(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/payments', [
                'customer_id' => $this->customer->id,
                'amount' => 250000,
                'payment_method' => 'bitcoin',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_can_list_payments(): void
    {
        // Create payment first
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/payments', [
                'customer_id' => $this->customer->id,
                'amount' => 250000,
                'payment_method' => 'cash',
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/payments');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }
}
