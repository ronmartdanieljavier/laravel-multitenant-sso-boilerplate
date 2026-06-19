<?php

namespace App\Auth\Tests;

use App\Auth\Enums\Role;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AppPickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/apps')->assertUnauthorized();
    }

    public function test_returns_empty_apps_list_when_user_has_no_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/apps')
            ->assertOk()
            ->assertJson([]);
    }

    public function test_returns_apps_with_clients_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::Admin]);
        $user->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::Admin,
            'is_default' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/apps')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.app_id', $app->id)
            ->assertJsonPath('0.slug', $app->slug)
            ->assertJsonPath('0.role', Role::Admin->value)
            ->assertJsonPath('0.tenants.0.tenant_id', $tenant->id)
            ->assertJsonPath('0.tenants.0.is_default', true);
    }

    public function test_user_only_sees_their_own_apps(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $appA = App::factory()->create();
        $appB = App::factory()->create();

        $userA->userApps()->create(['app_id' => $appA->id, 'role' => Role::User]);
        $userB->userApps()->create(['app_id' => $appB->id, 'role' => Role::User]);

        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/apps')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.app_id', $appA->id);
    }
}
