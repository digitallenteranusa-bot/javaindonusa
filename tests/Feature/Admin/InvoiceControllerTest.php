<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->actingAsAdmin();
    }

    public function test_invoice_index_page_loads(): void
    {
        Invoice::factory()->count(3)->create();

        $response = $this->get(route('admin.invoices.index'));

        $response->assertStatus(200);
    }

    public function test_invoice_index_with_status_filter(): void
    {
        Invoice::factory()->paid()->create();
        Invoice::factory()->pending()->create();

        $response = $this->get(route('admin.invoices.index', ['status' => 'paid']));

        $response->assertStatus(200);
    }

    public function test_generate_invoices(): void
    {
        // DATE_FORMAT is MySQL-only; skip on SQLite
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Generate invoices uses MySQL-specific DATE_FORMAT function');
        }

        $customer = Customer::factory()->create(['status' => 'active']);

        $response = $this->post(route('admin.invoices.generate'), [
            'month' => now()->month,
            'year' => now()->year,
        ]);

        $response->assertRedirect();
    }

    public function test_cancel_pending_invoice(): void
    {
        $invoice = Invoice::factory()->pending()->create([
            'paid_amount' => 0,
        ]);

        $response = $this->post(route('admin.invoices.cancel', $invoice), [
            'reason' => 'Salah generate',
        ]);

        $response->assertRedirect();
    }

    public function test_cannot_cancel_paid_invoice(): void
    {
        $invoice = Invoice::factory()->paid()->create();

        $response = $this->post(route('admin.invoices.cancel', $invoice), [
            'reason' => 'Coba cancel',
        ]);

        $response->assertRedirect();
        // Invoice should still exist and be paid
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
        ]);
    }

    public function test_invoice_show(): void
    {
        $invoice = Invoice::factory()->create();

        $response = $this->get(route('admin.invoices.show', $invoice));

        $response->assertStatus(200);
    }
}
