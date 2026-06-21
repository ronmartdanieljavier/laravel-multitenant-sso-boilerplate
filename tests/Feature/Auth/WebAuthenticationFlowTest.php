<?php

namespace Tests\Feature\Auth;

use App\Auth\Enums\Role;
use App\Models\Central\App;
use App\Models\Central\SystemSetting;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WebAuthenticationFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_view_login_page(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('Login/Index'));
    }

    public function test_authenticated_user_visiting_login_is_redirected_to_apps_when_multiple_apps(): void
    {
        $user = User::factory()->create();
        $appA = App::where('slug', 'admin')->first();
        $appB = App::where('slug', 'tenant')->first();
        $user->userApps()->create(['app_id' => $appA->id, 'role' => Role::Admin]);
        $user->userApps()->create(['app_id' => $appB->id, 'role' => Role::User]);

        $this->actingAs($user)->get('/login')->assertRedirect(route('apps'));
    }

    public function test_authenticated_user_visiting_login_is_redirected_directly_to_app_when_single_app(): void
    {
        $user = User::factory()->create();
        $app = App::where('slug', 'admin')->first();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::Admin]);

        $this->actingAs($user)->get('/login')->assertRedirect(route('admin'));
    }

    public function test_authenticated_user_with_no_apps_visiting_login_is_redirected_to_apps(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect(route('apps'));
    }

    public function test_login_redirects_to_apps_when_user_has_multiple_apps(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);
        $appA = App::where('slug', 'admin')->first();
        $appB = App::where('slug', 'tenant')->first();
        $user->userApps()->create(['app_id' => $appA->id, 'role' => Role::Admin]);
        $user->userApps()->create(['app_id' => $appB->id, 'role' => Role::User]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret'])
            ->assertRedirect(route('apps'));
    }

    public function test_login_redirects_directly_to_app_when_user_has_single_app(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);
        $app = App::where('slug', 'admin')->first();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::Admin]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret'])
            ->assertRedirect(route('admin'));
    }

    public function test_logout_clears_session_and_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_unauthenticated_user_cannot_access_logout(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }

    public function test_idle_timeout_minutes_is_shared_with_authenticated_pages(): void
    {
        SystemSetting::set('authentication_idle_time', '15');
        $user = User::factory()->create();
        $app = App::where('slug', 'admin')->first();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::Admin]);

        $this->actingAs($user)
            ->get('/apps')
            ->assertInertia(fn ($page) => $page->where('idleTimeoutMinutes', 15));
    }

    public function test_idle_timeout_is_null_for_guests(): void
    {
        $this->get('/login')
            ->assertInertia(fn ($page) => $page->where('idleTimeoutMinutes', null));
    }

    public function test_idle_timeout_defaults_to_30_when_setting_not_configured(): void
    {
        $user = User::factory()->create();
        $app = App::where('slug', 'admin')->first();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::Admin]);

        $this->actingAs($user)
            ->get('/apps')
            ->assertInertia(fn ($page) => $page->where('idleTimeoutMinutes', 30));
    }
}
