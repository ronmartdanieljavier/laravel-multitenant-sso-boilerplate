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
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ResolveTenantDatabaseMiddlewareTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeRequest(User $user, ?string $appSlug, ?string $tenantSlug, array $tokenAbilities = []): Request
    {
        $request = Request::create('/api/tenant/test', 'GET');
        $request->headers->set('Accept', 'application/json');

        if ($appSlug !== null) {
            $request->headers->set('X-App', $appSlug);
        }

        if ($tenantSlug !== null) {
            $request->headers->set('X-Tenant', $tenantSlug);
        }

        $token = $user->createToken('sso', $tokenAbilities);
        $accessToken = PersonalAccessToken::findToken($token->plainTextToken);
        $user->withAccessToken($accessToken);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_returns_bad_request_when_no_app_header(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $request = $this->makeRequest($user, null, $tenant->slug);

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_returns_bad_request_when_no_tenant_header(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $request = $this->makeRequest($user, $app->slug, null, ["app:{$app->slug}"]);

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_app_not_found(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $request = $this->makeRequest($user, 'nonexistent-app', $tenant->slug, ['app:nonexistent-app']);

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_token_lacks_app_ability(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $request = $this->makeRequest($user, $app->slug, $tenant->slug, []);

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_user_has_no_user_app_record(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $request = $this->makeRequest($user, $app->slug, $tenant->slug, ["app:{$app->slug}"]);

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_tenant_not_found(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::User]);
        $request = $this->makeRequest($user, $app->slug, 'nonexistent-tenant', ["app:{$app->slug}"]);

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

        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::User]);

        $otherUser->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $request = $this->makeRequest($user, $app->slug, $tenant->slug, ["app:{$app->slug}"]);

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_tenant_belongs_to_different_app(): void
    {
        $user = User::factory()->create();
        $appA = App::factory()->create();
        $appB = App::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->userApps()->create(['app_id' => $appB->id, 'role' => Role::User]);
        $user->userAppTenants()->create([
            'app_id' => $appA->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $request = $this->makeRequest($user, $appB->slug, $tenant->slug, ["app:{$appB->slug}"]);

        $middleware = new ResolveTenantDatabase;
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_tenant_is_inactive(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => false]);

        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::User]);
        $user->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $request = $this->makeRequest($user, $app->slug, $tenant->slug, ["app:{$app->slug}"]);

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

        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::Admin]);
        $user->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::Admin,
            'is_default' => true,
        ]);

        $request = $this->makeRequest($user, $app->slug, $tenant->slug, ["app:{$app->slug}"]);

        DB::shouldReceive('purge')->once()->with('tenant');
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
        $this->assertEquals($tenant->slug, $request->attributes->get('current_tenant')->slug);
    }

    public function test_stores_current_app_and_role_on_request(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::User]);
        $user->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $request = $this->makeRequest($user, $app->slug, $tenant->slug, ["app:{$app->slug}"]);

        DB::shouldReceive('purge')->once()->with('tenant');

        $middleware = new ResolveTenantDatabase;
        $middleware->handle($request, fn () => new Response('ok'));

        $this->assertEquals($app->id, $request->attributes->get('current_app')->id);
        $this->assertEquals($tenant->id, $request->attributes->get('current_tenant')->id);
        $this->assertEquals(Role::User, $request->attributes->get('current_role'));
    }
}
