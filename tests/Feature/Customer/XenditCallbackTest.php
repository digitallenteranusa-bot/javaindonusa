<?php

namespace Tests\Feature\Customer;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XenditCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('xendit', 'webhook_token', 'valid-callback-token');
    }

    public function test_invalid_token_returns_403(): void
    {
        $response = $this->postJson('/api/xendit/callback', [
            'status' => 'PAID',
            'external_id' => 'INV-202401-00001',
        ], [
            'X-Callback-Token' => 'invalid-token',
        ]);

        $response->assertStatus(403);
    }

    public function test_callback_endpoint_exists(): void
    {
        $response = $this->postJson('/api/xendit/callback', [
            'status' => 'PAID',
        ], [
            'X-Callback-Token' => 'valid-callback-token',
        ]);

        // Should not be 404 or 405
        $this->assertNotEquals(404, $response->status());
        $this->assertNotEquals(405, $response->status());
    }

    public function test_callback_without_token_returns_403(): void
    {
        $response = $this->postJson('/api/xendit/callback', [
            'status' => 'PAID',
            'external_id' => 'INV-202401-00001',
        ]);

        $response->assertStatus(403);
    }

    public function test_callback_with_empty_data(): void
    {
        $response = $this->postJson('/api/xendit/callback', [], [
            'X-Callback-Token' => 'valid-callback-token',
        ]);

        // Should handle empty data gracefully (400 or 500)
        $this->assertContains($response->status(), [400, 500]);
    }
}
