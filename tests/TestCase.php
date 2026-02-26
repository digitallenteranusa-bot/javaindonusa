<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
    /**
     * Login sebagai admin dan return User instance.
     */
    protected function actingAsAdmin(?User $user = null): User
    {
        $admin = $user ?? User::factory()->admin()->create();
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * Login sebagai collector/penagih dan return User instance.
     */
    protected function actingAsCollector(?User $user = null): User
    {
        $collector = $user ?? User::factory()->collector()->create();
        $this->actingAs($collector);

        return $collector;
    }
}
