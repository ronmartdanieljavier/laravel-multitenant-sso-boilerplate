<?php

namespace App\Auth\Tests;

use App\Auth\Data\AuthTokenData;
use App\Auth\Data\LoginCredentialsData;
use App\Auth\Enums\Role;
use App\Auth\Services\AuthService;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AuthService::class);
    }

    public function test_login_returns_auth_token_data_for_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret')]);

        $result = $this->service->login(new LoginCredentialsData(
            email: $user->email,
            password: 'secret',
        ));

        $this->assertInstanceOf(AuthTokenData::class, $result);
        $this->assertSame('Bearer', $result->tokenType);
        $this->assertSame($user->id, $result->user->id);
        $this->assertSame($user->name, $result->user->name);
        $this->assertSame($user->email, $result->user->email);
        $this->assertNotEmpty($result->token);
    }

    public function test_login_token_includes_app_slug_abilities(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret')]);
        $app = App::where('slug', 'admin')->first();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::Admin]);

        $this->service->login(new LoginCredentialsData(
            email: $user->email,
            password: 'secret',
        ));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'sso',
            'abilities' => '["app:admin"]',
        ]);
    }

    public function test_login_includes_app_and_tenant_access_in_result(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret')]);
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::User]);
        $user->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $result = $this->service->login(new LoginCredentialsData(
            email: $user->email,
            password: 'secret',
        ));

        $apps = $result->apps->toCollection();
        $this->assertCount(1, $apps);
        $this->assertSame($app->id, $apps->first()->appId);
        $tenants = $apps->first()->tenants->toCollection();
        $this->assertCount(1, $tenants);
        $this->assertSame($tenant->id, $tenants->first()->tenantId);
        $this->assertTrue($tenants->first()->isDefault);
    }

    public function test_login_throws_for_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret')]);

        $this->expectException(AuthenticationException::class);

        $this->service->login(new LoginCredentialsData(
            email: $user->email,
            password: 'wrong-password',
        ));
    }

    public function test_login_throws_for_unknown_email(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->service->login(new LoginCredentialsData(
            email: 'ghost@example.com',
            password: 'secret',
        ));
    }
}
