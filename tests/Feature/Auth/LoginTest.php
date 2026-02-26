<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_valid_login_redirects_admin_to_dashboard(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'username' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_valid_login_redirects_collector_to_collector_dashboard(): void
    {
        $user = User::factory()->collector()->create([
            'email' => 'collector@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'username' => 'collector@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('collector.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_returns_error(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'username' => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->admin()->create([
            'email' => 'inactive@test.com',
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'username' => 'inactive@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_logout_redirects_to_login(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
