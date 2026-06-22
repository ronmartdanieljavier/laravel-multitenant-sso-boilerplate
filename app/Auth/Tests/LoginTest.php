<?php

namespace App\Auth\Tests;

use App\Auth\Enums\Role;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'name', 'email'],
                'apps',
            ])
            ->assertJson(['token_type' => 'Bearer']);
    }

    public function test_login_returns_app_and_client_access(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::User]);
        $user->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('apps.0.app_id', $app->id)
            ->assertJsonPath('apps.0.tenants.0.tenant_id', $tenant->id)
            ->assertJsonPath('apps.0.tenants.0.is_default', true);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'ghost@example.com',
            'password' => 'anything',
        ])->assertUnauthorized();
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret',
        ]);

        $this->withToken($loginResponse->json('token'))
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);

        $this->assertDatabaseEmpty('personal_access_tokens');
    }
}
