<?php

namespace Tests\Feature\Tenant;

use App\Auth\Enums\Role;
use App\Http\Middleware\ResolveTenantDatabase;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResolveTenantDatabaseMiddlewareTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeRequest(User $user, ?string $tenantSlug): Request
    {
        $request = Request::create('/api/tenant/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        if ($tenantSlug !== null) {
            $request->headers->set('X-Tenant', $tenantSlug);
        }
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_returns_bad_request_when_no_tenant_header(): void
    {
        $user = User::factory()->create();
        $request = $this->makeRequest($user, null);

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_tenant_not_found(): void
    {
        $user = User::factory()->create();
        $request = $this->makeRequest($user, 'nonexistent-tenant');

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_user_has_no_access_to_tenant(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();

        $otherUser->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $request = $this->makeRequest($user, $tenant->slug);

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_tenant_is_inactive(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => false]);

        $user->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $request = $this->makeRequest($user, $tenant->slug);

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_configures_tenant_database_connection_for_authorized_user(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create([
            'db_host' => '10.0.0.1',
            'db_port' => 3307,
            'db_name' => 'tenant_db',
            'db_username' => 'tenant_user',
            'db_password' => 'secret',
        ]);

        $user->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::Admin,
            'is_default' => true,
        ]);

        DB::shouldReceive('purge')->once()->with('tenant');

        $request = $this->makeRequest($user, $tenant->slug);
        $calledNext = false;

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, function (Request $req) use (&$calledNext) {
            $calledNext = true;

            return new Response('ok');
        });

        $this->assertTrue($calledNext);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('10.0.0.1', Config::get('database.connections.tenant.host'));
        $this->assertEquals(3307, Config::get('database.connections.tenant.port'));
        $this->assertEquals('tenant_db', Config::get('database.connections.tenant.database'));
        $this->assertEquals('tenant_user', Config::get('database.connections.tenant.username'));
        $this->assertEquals($tenant->slug, $request->get('current_tenant')->slug);
    }
}
