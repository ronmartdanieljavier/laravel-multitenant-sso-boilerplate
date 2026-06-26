<?php

namespace Tests\Feature\Tenant;

use App\Auth\Enums\Role;
use App\Http\Middleware\ResolveWebTenantDatabase;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenant\Services\TenantSwitcherService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ResolveWebTenantDatabaseMiddlewareTest extends TestCase
{
    use LazilyRefreshDatabase;

    private App $tenantApp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantApp = App::where('slug', 'tenant')->first()
            ?? App::factory()->create(['slug' => 'tenant', 'is_active' => true]);
    }

    private function makeAuthedRequest(User $user): Request
    {
        $request = Request::create('/documents', 'GET');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function middleware(?string $tenantSlug): ResolveWebTenantDatabase
    {
        $switcher = $this->createMock(TenantSwitcherService::class);
        $switcher->method('getCurrentTenantSlug')->willReturn($tenantSlug);
        $this->app->instance(TenantSwitcherService::class, $switcher);

        return app(ResolveWebTenantDatabase::class);
    }

    private function grantAccess(User $user, Tenant $tenant): void
    {
        $user->userApps()->firstOrCreate(['app_id' => $this->tenantApp->id], ['role' => Role::User]);
        $user->userAppTenants()->create([
            'app_id' => $this->tenantApp->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);
    }

    public function test_aborts_503_when_tenant_app_not_configured(): void
    {
        $this->tenantApp->delete();
        $user = User::factory()->create();

        $this->expectException(HttpException::class);
        $this->middleware('some-tenant')->handle($this->makeAuthedRequest($user), fn () => new Response('ok'));
    }

    public function test_aborts_403_when_no_tenant_in_session(): void
    {
        $user = User::factory()->create();

        $this->expectException(HttpException::class);
        $this->middleware(null)->handle($this->makeAuthedRequest($user), fn () => new Response('ok'));
    }

    public function test_aborts_403_when_user_has_no_access_to_tenant(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->grantAccess($other, $tenant);

        $this->expectException(HttpException::class);
        $this->middleware($tenant->slug)->handle($this->makeAuthedRequest($user), fn () => new Response('ok'));
    }

    public function test_aborts_503_when_tenant_is_in_maintenance(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => true]);
        $user = User::factory()->create();

        $this->grantAccess($user, $tenant);

        $this->expectException(HttpException::class);
        $this->middleware($tenant->slug)->handle($this->makeAuthedRequest($user), fn () => new Response('ok'));
    }

    public function test_configures_mysql_connection_for_authorized_user(): void
    {
        Config::set('database.connections.tenant.driver', 'mysql');

        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'is_maintenance' => false,
            'db_host' => '10.0.0.1',
            'db_port' => 3307,
            'db_name' => 'tenant_mysql',
            'db_username' => 'mysql_user',
            'db_password' => 'mysql_pass',
            'read_replica_host' => null,
        ]);
        $user = User::factory()->create();

        $this->grantAccess($user, $tenant);

        $request = $this->makeAuthedRequest($user);

        DB::shouldReceive('purge')->once()->with('tenant');

        $called = false;
        $this->middleware($tenant->slug)->handle($request, function () use (&$called) {
            $called = true;

            return new Response('ok');
        });

        $this->assertTrue($called);
        $this->assertEquals('mysql', Config::get('database.connections.tenant.driver'));
        $this->assertEquals('10.0.0.1', Config::get('database.connections.tenant.host'));
        $this->assertEquals(3307, Config::get('database.connections.tenant.port'));
        $this->assertEquals('tenant_mysql', Config::get('database.connections.tenant.database'));
        $this->assertEquals('utf8mb4', Config::get('database.connections.tenant.charset'));
        $this->assertEquals($tenant->slug, $request->attributes->get('current_tenant')->slug);
    }

    public function test_configures_pgsql_connection_when_driver_is_pgsql(): void
    {
        Config::set('database.connections.tenant.driver', 'pgsql');

        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'is_maintenance' => false,
            'db_host' => '10.0.0.5',
            'db_port' => 5432,
            'db_name' => 'tenant_pg',
            'db_username' => 'pg_user',
            'db_password' => 'pg_pass',
            'read_replica_host' => null,
        ]);
        $user = User::factory()->create();

        $this->grantAccess($user, $tenant);

        $request = $this->makeAuthedRequest($user);

        DB::shouldReceive('purge')->once()->with('tenant');

        $this->middleware($tenant->slug)->handle($request, fn () => new Response('ok'));

        $this->assertEquals('pgsql', Config::get('database.connections.tenant.driver'));
        $this->assertEquals('10.0.0.5', Config::get('database.connections.tenant.host'));
        $this->assertEquals(5432, Config::get('database.connections.tenant.port'));
        $this->assertEquals('tenant_pg', Config::get('database.connections.tenant.database'));
        $this->assertEquals('public', Config::get('database.connections.tenant.schema'));
        $this->assertNull(Config::get('database.connections.tenant.collation'));
        $this->assertEquals($tenant->slug, $request->attributes->get('current_tenant')->slug);
    }

    public function test_pgsql_configures_read_write_split(): void
    {
        Config::set('database.connections.tenant.driver', 'pgsql');

        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'is_maintenance' => false,
            'db_host' => '10.0.0.1',
            'db_port' => 5432,
            'db_name' => 'tenant_pg',
            'db_username' => 'pg_user',
            'db_password' => 'pg_pass',
            'read_replica_host' => '10.0.0.2',
            'read_replica_port' => 5433,
        ]);
        $user = User::factory()->create();

        $this->grantAccess($user, $tenant);

        DB::shouldReceive('purge')->once()->with('tenant');

        $this->middleware($tenant->slug)->handle($this->makeAuthedRequest($user), fn () => new Response('ok'));

        $this->assertEquals('pgsql', Config::get('database.connections.tenant.driver'));
        $this->assertEquals('10.0.0.1', Config::get('database.connections.tenant.write.host'));
        $this->assertEquals(5432, Config::get('database.connections.tenant.write.port'));
        $this->assertEquals('10.0.0.2', Config::get('database.connections.tenant.read.host'));
        $this->assertEquals(5433, Config::get('database.connections.tenant.read.port'));
        $this->assertTrue(Config::get('database.connections.tenant.sticky'));
    }

    public function test_mysql_configures_read_write_split(): void
    {
        Config::set('database.connections.tenant.driver', 'mysql');

        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'is_maintenance' => false,
            'db_host' => '10.0.0.1',
            'db_port' => 3307,
            'db_name' => 'tenant_mysql',
            'db_username' => 'mysql_user',
            'db_password' => 'mysql_pass',
            'read_replica_host' => '10.0.0.2',
            'read_replica_port' => 3308,
        ]);
        $user = User::factory()->create();

        $this->grantAccess($user, $tenant);

        DB::shouldReceive('purge')->once()->with('tenant');

        $this->middleware($tenant->slug)->handle($this->makeAuthedRequest($user), fn () => new Response('ok'));

        $this->assertEquals('mysql', Config::get('database.connections.tenant.driver'));
        $this->assertEquals('10.0.0.1', Config::get('database.connections.tenant.write.host'));
        $this->assertEquals(3307, Config::get('database.connections.tenant.write.port'));
        $this->assertEquals('10.0.0.2', Config::get('database.connections.tenant.read.host'));
        $this->assertEquals(3308, Config::get('database.connections.tenant.read.port'));
        $this->assertTrue(Config::get('database.connections.tenant.sticky'));
    }

    public function test_sets_current_app_and_tenant_on_request(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false, 'read_replica_host' => null]);
        $user = User::factory()->create();

        $this->grantAccess($user, $tenant);

        $request = $this->makeAuthedRequest($user);

        DB::shouldReceive('purge')->once()->with('tenant');

        $this->middleware($tenant->slug)->handle($request, fn () => new Response('ok'));

        $this->assertEquals($this->tenantApp->id, $request->attributes->get('current_app')->id);
        $this->assertEquals($tenant->id, $request->attributes->get('current_tenant')->id);
    }
}
