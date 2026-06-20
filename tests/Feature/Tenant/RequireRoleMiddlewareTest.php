<?php

namespace Tests\Feature\Tenant;

use App\Auth\Enums\Role;
use App\Http\Middleware\RequireRole;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class RequireRoleMiddlewareTest extends TestCase
{
    private function makeRequest(Role $role): Request
    {
        $request = Request::create('/api/tenant/test', 'GET');
        $request->attributes->set('current_role', $role);

        return $request;
    }

    public function test_admin_passes_admin_check(): void
    {
        $request = $this->makeRequest(Role::Admin);
        $middleware = new RequireRole;
        $response = $middleware->handle($request, fn () => new Response('ok'), 'admin');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_admin_passes_user_check(): void
    {
        $request = $this->makeRequest(Role::Admin);
        $middleware = new RequireRole;
        $response = $middleware->handle($request, fn () => new Response('ok'), 'user');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_admin_passes_readonly_check(): void
    {
        $request = $this->makeRequest(Role::Admin);
        $middleware = new RequireRole;
        $response = $middleware->handle($request, fn () => new Response('ok'), 'readonly');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_user_fails_admin_check(): void
    {
        $request = $this->makeRequest(Role::User);
        $middleware = new RequireRole;
        $response = $middleware->handle($request, fn () => new Response('ok'), 'admin');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_user_passes_user_check(): void
    {
        $request = $this->makeRequest(Role::User);
        $middleware = new RequireRole;
        $response = $middleware->handle($request, fn () => new Response('ok'), 'user');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_user_passes_readonly_check(): void
    {
        $request = $this->makeRequest(Role::User);
        $middleware = new RequireRole;
        $response = $middleware->handle($request, fn () => new Response('ok'), 'readonly');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_readonly_fails_admin_check(): void
    {
        $request = $this->makeRequest(Role::Readonly);
        $middleware = new RequireRole;
        $response = $middleware->handle($request, fn () => new Response('ok'), 'admin');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_readonly_fails_user_check(): void
    {
        $request = $this->makeRequest(Role::Readonly);
        $middleware = new RequireRole;
        $response = $middleware->handle($request, fn () => new Response('ok'), 'user');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_readonly_passes_readonly_check(): void
    {
        $request = $this->makeRequest(Role::Readonly);
        $middleware = new RequireRole;
        $response = $middleware->handle($request, fn () => new Response('ok'), 'readonly');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_no_role_set_on_request(): void
    {
        $request = Request::create('/api/tenant/test', 'GET');
        $middleware = new RequireRole;
        $response = $middleware->handle($request, fn () => new Response('ok'), 'user');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_passes_when_role_matches_any_of_multiple_accepted_roles(): void
    {
        $request = $this->makeRequest(Role::User);
        $middleware = new RequireRole;
        $response = $middleware->handle($request, fn () => new Response('ok'), 'admin', 'user');

        $this->assertEquals(200, $response->getStatusCode());
    }
}
