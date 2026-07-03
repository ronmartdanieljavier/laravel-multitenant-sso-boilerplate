<?php

namespace Tests\Feature;

use App\Auth\Enums\Role;
use App\Models\Central\App;
use App\Models\Central\User;
use App\Models\Central\UserApp;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_access_horizon(): void
    {
        $this->get('/horizon')->assertForbidden();
    }

    public function test_user_without_admin_role_cannot_access_horizon(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/horizon')->assertForbidden();
    }

    public function test_user_with_non_admin_app_role_cannot_access_horizon(): void
    {
        $user = User::factory()->create();

        UserApp::create([
            'user_id' => $user->id,
            'app_id' => $this->appBySlug('admin')->id,
            'role' => Role::User,
        ]);

        $this->actingAs($user)->get('/horizon')->assertForbidden();
    }

    public function test_user_with_admin_role_on_admin_app_can_access_horizon(): void
    {
        $user = User::factory()->create();

        UserApp::create([
            'user_id' => $user->id,
            'app_id' => $this->appBySlug('admin')->id,
            'role' => Role::Admin,
        ]);

        $this->actingAs($user)->get('/horizon')->assertOk();
    }

    public function test_admin_role_on_a_different_app_does_not_grant_horizon_access(): void
    {
        $user = User::factory()->create();

        UserApp::create([
            'user_id' => $user->id,
            'app_id' => $this->appBySlug('tenant')->id,
            'role' => Role::Admin,
        ]);

        $this->actingAs($user)->get('/horizon')->assertForbidden();
    }

    private function appBySlug(string $slug): App
    {
        return App::query()->firstOrCreate(
            ['slug' => $slug],
            App::factory()->make(['slug' => $slug])->toArray(),
        );
    }
}
