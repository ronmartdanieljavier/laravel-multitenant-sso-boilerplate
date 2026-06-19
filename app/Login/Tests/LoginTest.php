<?php

namespace App\Login\Tests;

use App\Login\Enums\Role;
use App\Login\Models\App;
use App\Login\Models\Client;
use App\Login\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $response = $this->postJson('/api/login', [
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
        $client = Client::factory()->create();

        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::User]);
        $user->userAppClients()->create([
            'app_id' => $app->id,
            'client_id' => $client->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('apps.0.app_id', $app->id)
            ->assertJsonPath('apps.0.clients.0.client_id', $client->id)
            ->assertJsonPath('apps.0.clients.0.is_default', true);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'ghost@example.com',
            'password' => 'anything',
        ])->assertUnauthorized();
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret',
        ]);

        $this->withToken($loginResponse->json('token'))
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);

        $this->assertDatabaseEmpty('personal_access_tokens');
    }
}
