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

    public function test_login_requires_email(): void
    {
        $this->post('/login', ['password' => 'secret'])
            ->assertSessionHasErrors('email');
    }

    public function test_login_requires_valid_email_format(): void
    {
        $this->post('/login', ['email' => 'not-an-email', 'password' => 'secret'])
            ->assertSessionHasErrors('email');
    }

    public function test_login_requires_password(): void
    {
        $this->post('/login', ['email' => 'user@example.com'])
            ->assertSessionHasErrors('password');
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        User::factory()->create(['email' => 'user@example.com', 'password' => bcrypt('secret')]);

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_can_view_app_picker(): void
    {
        $user = User::factory()->create();
        $appA = App::where('slug', 'admin')->first();
        $appB = App::where('slug', 'tenant')->first();
        $user->userApps()->create(['app_id' => $appA->id, 'role' => Role::Admin]);
        $user->userApps()->create(['app_id' => $appB->id, 'role' => Role::User]);

        $this->actingAs($user)
            ->get('/apps')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/AppPicker')->has('apps', 2));
    }

    public function test_app_picker_redirects_to_login_when_user_has_no_apps(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/apps')->assertRedirect(route('login'));
    }

    public function test_select_app_redirects_to_admin(): void
    {
        $user = User::factory()->create();
        $app = App::where('slug', 'admin')->first();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::Admin]);

        $this->actingAs($user)
            ->post('/apps/select', ['slug' => 'admin'])
            ->assertRedirect(route('admin'));
    }

    public function test_select_app_redirects_to_tenant(): void
    {
        $user = User::factory()->create();
        $app = App::where('slug', 'tenant')->first();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::User]);

        $this->actingAs($user)
            ->post('/apps/select', ['slug' => 'tenant'])
            ->assertRedirect(route('tenant'));
    }

    public function test_select_app_rejects_app_user_does_not_have_access_to(): void
    {
        $user = User::factory()->create();
        $app = App::where('slug', 'admin')->first();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::Admin]);

        $this->actingAs($user)
            ->post('/apps/select', ['slug' => 'tenant'])
            ->assertSessionHasErrors('slug');
    }

    public function test_select_app_requires_slug(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/apps/select', [])
            ->assertSessionHasErrors('slug');
    }

    public function test_unauthenticated_user_cannot_select_app(): void
    {
        $this->post('/apps/select', ['slug' => 'admin'])->assertRedirect(route('login'));
    }
}
