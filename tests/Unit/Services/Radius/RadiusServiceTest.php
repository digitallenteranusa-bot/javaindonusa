<?php

namespace Tests\Unit\Services\Radius;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Radius\Nas;
use App\Models\Radius\RadCheck;
use App\Models\Radius\RadReply;
use App\Models\Radius\RadUserGroup;
use App\Models\RadiusServer;
use App\Models\Router;
use App\Services\Radius\RadiusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RadiusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RadiusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();

        // Enable RADIUS for tests
        config([
            'radius.enabled' => true,
            'radius.isolation_method' => 'rate_limit',
            'radius.isolation_rate_limit' => '1k/1k',
            'radius.default_group' => 'default',
            'radius.attributes.rate_limit' => 'Mikrotik-Rate-Limit',
        ]);

        // Create FreeRADIUS tables in the radius connection (SQLite via phpunit.xml)
        $this->createRadiusTables();

        $this->service = new RadiusService();
    }

    private function createRadiusTables(): void
    {
        $conn = Schema::connection('radius');

        if (!$conn->hasTable('radcheck')) {
            $conn->create('radcheck', function ($table) {
                $table->id();
                $table->string('username', 64)->index();
                $table->string('attribute', 64);
                $table->char('op', 2)->default(':=');
                $table->string('value', 253);
            });
        }

        if (!$conn->hasTable('radreply')) {
            $conn->create('radreply', function ($table) {
                $table->id();
                $table->string('username', 64)->index();
                $table->string('attribute', 64);
                $table->char('op', 2)->default(':=');
                $table->string('value', 253);
            });
        }

        if (!$conn->hasTable('radusergroup')) {
            $conn->create('radusergroup', function ($table) {
                $table->id();
                $table->string('username', 64)->index();
                $table->string('groupname', 64);
                $table->integer('priority')->default(1);
            });
        }

        if (!$conn->hasTable('radacct')) {
            $conn->create('radacct', function ($table) {
                $table->bigIncrements('radacctid');
                $table->string('acctsessionid', 64)->default('');
                $table->string('acctuniqueid', 32)->default('');
                $table->string('username', 64)->index();
                $table->string('realm', 64)->default('');
                $table->string('nasipaddress', 15)->default('');
                $table->unsignedInteger('nasportid')->nullable();
                $table->string('nasporttype', 32)->nullable();
                $table->timestamp('acctstarttime')->nullable();
                $table->timestamp('acctupdatetime')->nullable();
                $table->timestamp('acctstoptime')->nullable();
                $table->unsignedInteger('acctinterval')->nullable();
                $table->unsignedInteger('acctsessiontime')->nullable();
                $table->string('acctauthentic', 32)->nullable();
                $table->string('connectinfo_start', 50)->nullable();
                $table->string('connectinfo_stop', 50)->nullable();
                $table->bigInteger('acctinputoctets')->nullable();
                $table->bigInteger('acctoutputoctets')->nullable();
                $table->string('calledstationid', 50)->default('');
                $table->string('callingstationid', 50)->default('');
                $table->string('acctterminatecause', 32)->default('');
                $table->string('servicetype', 32)->nullable();
                $table->string('framedprotocol', 32)->nullable();
                $table->string('framedipaddress', 15)->default('');
            });
        }

        if (!$conn->hasTable('nas')) {
            $conn->create('nas', function ($table) {
                $table->id();
                $table->string('nasname', 128)->index();
                $table->string('shortname', 32)->nullable();
                $table->string('type', 30)->default('other');
                $table->integer('ports')->nullable();
                $table->string('secret', 60)->default('secret');
                $table->string('server', 64)->nullable();
                $table->string('community', 50)->nullable();
                $table->string('description', 200)->default('RADIUS Client');
            });
        }
    }

    // ================================================================
    // DISABLED RADIUS
    // ================================================================

    public function test_returns_false_when_disabled(): void
    {
        Config::set('radius.enabled', false);

        $customer = Customer::factory()->create(['pppoe_username' => 'testuser']);

        $this->assertFalse($this->service->syncCustomer($customer));
        $this->assertFalse($this->service->removeCustomer($customer));
        $this->assertFalse($this->service->isolateCustomer($customer));
        $this->assertFalse($this->service->reopenCustomer($customer));
    }

    public function test_is_enabled_returns_config_value(): void
    {
        Config::set('radius.enabled', true);
        $this->assertTrue($this->service->isEnabled());

        Config::set('radius.enabled', false);
        $this->assertFalse($this->service->isEnabled());
    }

    // ================================================================
    // SYNC CUSTOMER
    // ================================================================

    public function test_sync_customer_creates_radcheck_entry(): void
    {
        $customer = Customer::factory()->create([
            'pppoe_username' => 'john@isp.net',
            'pppoe_password' => 'secret123',
        ]);

        $result = $this->service->syncCustomer($customer);

        $this->assertTrue($result);
        $this->assertDatabaseHas('radcheck', [
            'username' => 'john@isp.net',
            'attribute' => 'Cleartext-Password',
            'op' => ':=',
        ], 'radius');
    }

    public function test_sync_customer_creates_radreply_with_rate_limit(): void
    {
        $package = Package::factory()->create([
            'speed_download' => 10240,
            'speed_upload' => 5120,
        ]);
        $customer = Customer::factory()->create([
            'pppoe_username' => 'john@isp.net',
            'package_id' => $package->id,
        ]);

        $this->service->syncCustomer($customer);

        $this->assertDatabaseHas('radreply', [
            'username' => 'john@isp.net',
            'attribute' => 'Mikrotik-Rate-Limit',
            'op' => ':=',
            'value' => '5120k/10240k',
        ], 'radius');
    }

    public function test_sync_customer_creates_radusergroup(): void
    {
        $customer = Customer::factory()->create([
            'pppoe_username' => 'john@isp.net',
        ]);

        $this->service->syncCustomer($customer);

        $this->assertDatabaseHas('radusergroup', [
            'username' => 'john@isp.net',
            'groupname' => 'default',
        ], 'radius');
    }

    public function test_sync_customer_replaces_existing_entries(): void
    {
        $customer = Customer::factory()->create([
            'pppoe_username' => 'john@isp.net',
        ]);

        // Sync twice
        $this->service->syncCustomer($customer);
        $this->service->syncCustomer($customer);

        // Should only have 1 entry each
        $this->assertEquals(1, RadCheck::forUser('john@isp.net')->count());
        $this->assertEquals(1, RadUserGroup::forUser('john@isp.net')->count());
    }

    public function test_sync_customer_skips_without_username(): void
    {
        $customer = Customer::factory()->create([
            'pppoe_username' => null,
        ]);

        $result = $this->service->syncCustomer($customer);

        $this->assertFalse($result);
    }

    // ================================================================
    // REMOVE CUSTOMER
    // ================================================================

    public function test_remove_customer_deletes_all_entries(): void
    {
        $customer = Customer::factory()->create([
            'pppoe_username' => 'john@isp.net',
        ]);

        $this->service->syncCustomer($customer);
        $this->service->removeCustomer($customer);

        $this->assertEquals(0, RadCheck::forUser('john@isp.net')->count());
        $this->assertEquals(0, RadReply::forUser('john@isp.net')->count());
        $this->assertEquals(0, RadUserGroup::forUser('john@isp.net')->count());
    }

    // ================================================================
    // ISOLATE CUSTOMER
    // ================================================================

    public function test_isolate_customer_changes_rate_limit(): void
    {
        $customer = Customer::factory()->create([
            'pppoe_username' => 'john@isp.net',
        ]);

        $this->service->syncCustomer($customer);
        $this->service->isolateCustomer($customer);

        $rateLimitEntry = RadReply::forUser('john@isp.net')
            ->where('attribute', 'Mikrotik-Rate-Limit')
            ->first();

        $this->assertNotNull($rateLimitEntry);
        $this->assertEquals('1k/1k', $rateLimitEntry->value);
    }

    public function test_isolate_with_delete_method_removes_all(): void
    {
        Config::set('radius.isolation_method', 'delete');

        $customer = Customer::factory()->create([
            'pppoe_username' => 'john@isp.net',
        ]);

        $this->service->syncCustomer($customer);
        $this->service->isolateCustomer($customer);

        $this->assertEquals(0, RadCheck::forUser('john@isp.net')->count());
        $this->assertEquals(0, RadReply::forUser('john@isp.net')->count());
    }

    // ================================================================
    // REOPEN CUSTOMER
    // ================================================================

    public function test_reopen_customer_restores_rate_limit(): void
    {
        $package = Package::factory()->create([
            'speed_download' => 20480,
            'speed_upload' => 10240,
        ]);
        $customer = Customer::factory()->create([
            'pppoe_username' => 'john@isp.net',
            'package_id' => $package->id,
        ]);

        $this->service->syncCustomer($customer);
        $this->service->isolateCustomer($customer);
        $this->service->reopenCustomer($customer);

        $rateLimitEntry = RadReply::forUser('john@isp.net')
            ->where('attribute', 'Mikrotik-Rate-Limit')
            ->first();

        $this->assertNotNull($rateLimitEntry);
        $this->assertEquals('10240k/20480k', $rateLimitEntry->value);
    }

    public function test_reopen_restores_default_group(): void
    {
        $customer = Customer::factory()->create([
            'pppoe_username' => 'john@isp.net',
        ]);

        $this->service->syncCustomer($customer);
        $this->service->isolateCustomer($customer);
        $this->service->reopenCustomer($customer);

        $group = RadUserGroup::forUser('john@isp.net')->first();
        $this->assertNotNull($group);
        $this->assertEquals('default', $group->groupname);
    }

    // ================================================================
    // REMOVE BY USERNAME
    // ================================================================

    public function test_remove_by_username_clears_entries(): void
    {
        $customer = Customer::factory()->create([
            'pppoe_username' => 'olduser@isp.net',
        ]);

        $this->service->syncCustomer($customer);
        $this->service->removeByUsername('olduser@isp.net');

        $this->assertEquals(0, RadCheck::forUser('olduser@isp.net')->count());
        $this->assertEquals(0, RadReply::forUser('olduser@isp.net')->count());
        $this->assertEquals(0, RadUserGroup::forUser('olduser@isp.net')->count());
    }

    // ================================================================
    // SYNC NAS
    // ================================================================

    public function test_sync_nas_creates_entry(): void
    {
        $radiusServer = RadiusServer::create([
            'name' => 'Test RADIUS',
            'ip_address' => '10.0.0.1',
            'auth_port' => 1812,
            'acct_port' => 1813,
            'secret' => 'testing123',
            'status' => 'active',
        ]);
        $router = Router::factory()->create([
            'ip_address' => '192.168.1.1',
            'identity' => 'MK-Router1',
            'radius_server_id' => $radiusServer->id,
        ]);

        $result = $this->service->syncNas($router);

        $this->assertTrue($result);
        $this->assertDatabaseHas('nas', [
            'nasname' => '192.168.1.1',
            'shortname' => 'MK-Router1',
        ], 'radius');
    }

    public function test_sync_nas_skips_inactive_radius_server(): void
    {
        $radiusServer = RadiusServer::create([
            'name' => 'Test RADIUS',
            'ip_address' => '10.0.0.1',
            'auth_port' => 1812,
            'acct_port' => 1813,
            'secret' => 'testing123',
            'status' => 'inactive',
        ]);
        $router = Router::factory()->create([
            'radius_server_id' => $radiusServer->id,
        ]);

        $result = $this->service->syncNas($router);

        $this->assertFalse($result);
    }

    // ================================================================
    // BULK SYNC
    // ================================================================

    public function test_sync_all_customers_syncs_active_customers(): void
    {
        Customer::factory()->count(3)->create([
            'status' => 'active',
            'pppoe_username' => fn () => fake()->unique()->userName(),
        ]);
        Customer::factory()->create([
            'status' => 'terminated',
            'pppoe_username' => 'terminated@isp.net',
        ]);

        $stats = $this->service->syncAllCustomers();

        $this->assertEquals(3, $stats['synced']);
        $this->assertEquals(0, $stats['failed']);
    }

    public function test_sync_all_customers_handles_isolated(): void
    {
        Customer::factory()->create([
            'status' => 'isolated',
            'pppoe_username' => 'isolated@isp.net',
        ]);

        $stats = $this->service->syncAllCustomers();

        $this->assertEquals(1, $stats['synced']);

        // Should have isolation rate limit
        $rateLimit = RadReply::forUser('isolated@isp.net')
            ->where('attribute', 'Mikrotik-Rate-Limit')
            ->first();
        $this->assertEquals('1k/1k', $rateLimit->value);
    }

    // ================================================================
    // ONLINE CHECK
    // ================================================================

    public function test_is_online_returns_false_when_no_session(): void
    {
        $customer = Customer::factory()->create([
            'pppoe_username' => 'john@isp.net',
        ]);

        $this->assertFalse($this->service->isOnline($customer));
    }
}
