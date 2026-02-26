<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->actingAsAdmin();
    }

    public function test_payment_index_page_loads(): void
    {
        Payment::factory()->count(3)->create();

        $response = $this->get(route('admin.payments.index'));

        $response->assertStatus(200);
    }

    public function test_payment_store(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 200000]);
        Invoice::factory()->pending()->create([
            'customer_id' => $customer->id,
            'total_amount' => 200000,
            'remaining_amount' => 200000,
        ]);

        $response = $this->post(route('admin.payments.store'), [
            'customer_id' => $customer->id,
            'amount' => 200000,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();
    }

    public function test_payment_cancel_within_24h(): void
    {
        $payment = Payment::factory()->create([
            'status' => 'success',
            'created_at' => now(),
        ]);

        $response = $this->post(route('admin.payments.cancel', $payment), [
            'reason' => 'Salah input',
        ]);

        $response->assertRedirect();
    }

    public function test_payment_cancel_after_24h_rejected(): void
    {
        $payment = Payment::factory()->create([
            'status' => 'success',
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->post(route('admin.payments.cancel', $payment), [
            'reason' => 'Too late',
        ]);

        $response->assertRedirect();
        // Payment should remain unchanged
        $payment->refresh();
        $this->assertNotEquals('cancelled', $payment->status);
    }

    public function test_payment_index_with_date_filter(): void
    {
        Payment::factory()->create(['created_at' => now()]);
        Payment::factory()->create(['created_at' => now()->subMonth()]);

        $response = $this->get(route('admin.payments.index', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertStatus(200);
    }

    public function test_payment_store_validation(): void
    {
        $response = $this->post(route('admin.payments.store'), []);

        $response->assertSessionHasErrors(['customer_id', 'amount', 'payment_method']);
    }
}
