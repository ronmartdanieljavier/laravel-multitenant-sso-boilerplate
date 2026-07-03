<?php

namespace App\Admin\Tests;

use App\Admin\Mail\UserInvitationMail;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserManagementWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('admin.users'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_users_page(): void
    {
        $this->actingAs($this->grantAdminRole(User::factory()->create()))
            ->get(route('admin.users'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Index')
                ->has('users')
                ->has('apps')
                ->has('tenants')
                ->has('roles')
            );
    }

    public function test_admin_can_invite_user(): void
    {
        Mail::fake();
        $app = App::factory()->create();

        $this->actingAs($this->grantAdminRole(User::factory()->create()))
            ->post(route('admin.users.invite'), [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'apps' => [['app_id' => $app->id, 'role' => 'user', 'tenant_ids' => []]],
            ])
            ->assertRedirect(route('admin.users'));

        $invited = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($invited);
        $this->assertFalse($invited->is_active);
        $this->assertNotNull($invited->invitation_token);

        Mail::assertSent(UserInvitationMail::class, fn ($mail) => $mail->hasTo('jane@example.com'));
    }

    public function test_invite_rejects_duplicate_email(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->grantAdminRole(User::factory()->create()))
            ->post(route('admin.users.invite'), [
                'name' => 'Dup',
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_invite_rejects_missing_name(): void
    {
        $this->actingAs($this->grantAdminRole(User::factory()->create()))
            ->post(route('admin.users.invite'), ['email' => 'new@example.com'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_user(): void
    {
        $target = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
        $app = App::factory()->create();

        $this->actingAs($this->grantAdminRole(User::factory()->create()))
            ->put(route('admin.users.update', $target->id), [
                'name' => 'New Name',
                'email' => 'new@example.com',
                'apps' => [['app_id' => $app->id, 'role' => 'admin', 'tenant_ids' => []]],
            ])
            ->assertRedirect(route('admin.users'));

        $target->refresh();
        $this->assertSame('New Name', $target->name);
        $this->assertSame('new@example.com', $target->email);
        $this->assertDatabaseHas('user_apps', ['user_id' => $target->id, 'app_id' => $app->id, 'role' => 'admin']);
    }

    public function test_update_syncs_app_permissions(): void
    {
        $target = User::factory()->create();
        $app1 = App::factory()->create();
        $app2 = App::factory()->create();

        $target->userApps()->create(['app_id' => $app1->id, 'role' => 'user']);

        $this->actingAs($this->grantAdminRole(User::factory()->create()))
            ->put(route('admin.users.update', $target->id), [
                'name' => $target->name,
                'email' => $target->email,
                'apps' => [['app_id' => $app2->id, 'role' => 'user', 'tenant_ids' => []]],
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseMissing('user_apps', ['user_id' => $target->id, 'app_id' => $app1->id]);
        $this->assertDatabaseHas('user_apps', ['user_id' => $target->id, 'app_id' => $app2->id]);
    }

    public function test_invitation_accept_page_renders_for_valid_token(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'invitation_token' => 'valid-token-abc',
        ]);

        $this->get(route('invitation.accept', 'valid-token-abc'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Users/Accept'));
    }

    public function test_invitation_accept_page_redirects_for_invalid_token(): void
    {
        $this->get(route('invitation.accept', 'bad-token'))->assertRedirect(route('login'));
    }

    public function test_accepting_invitation_activates_account(): void
    {
        $user = User::factory()->create([
            'name' => 'Pending User',
            'is_active' => false,
            'invitation_token' => 'my-token-xyz',
        ]);

        $this->post(route('invitation.accept.submit', 'my-token-xyz'), [
            'name' => 'Active User',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->invitation_token);
        $this->assertSame('Active User', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_accepting_invitation_fails_with_short_password(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'invitation_token' => 'token-short',
        ]);

        $this->post(route('invitation.accept.submit', 'token-short'), [
            'name' => 'User',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_accepting_invitation_fails_with_mismatched_passwords(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'invitation_token' => 'token-mismatch',
        ]);

        $this->post(route('invitation.accept.submit', 'token-mismatch'), [
            'name' => 'User',
            'password' => 'password123',
            'password_confirmation' => 'password456',
        ])->assertSessionHasErrors('password');
    }

    public function test_invitation_accept_page_passes_email_and_name_props(): void
    {
        $user = User::factory()->create([
            'name' => 'Invited Person',
            'email' => 'invited@example.com',
            'is_active' => false,
            'invitation_token' => 'token-props-check',
        ]);

        $this->get(route('invitation.accept', 'token-props-check'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Accept')
                ->where('email', 'invited@example.com')
                ->where('name', 'Invited Person')
            );
    }

    public function test_active_user_cannot_use_invitation_token(): void
    {
        User::factory()->create([
            'is_active' => true,
            'invitation_token' => 'active-user-token',
        ]);

        $this->get(route('invitation.accept', 'active-user-token'))
            ->assertRedirect(route('login'));
    }

    public function test_update_syncs_tenant_assignments(): void
    {
        $target = User::factory()->create();
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->grantAdminRole(User::factory()->create()))
            ->put(route('admin.users.update', $target->id), [
                'name' => $target->name,
                'email' => $target->email,
                'apps' => [['app_id' => $app->id, 'role' => 'user', 'tenant_ids' => [$tenant->id]]],
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('user_app_tenants', [
            'user_id' => $target->id,
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_resend_invitation_sends_email_and_redirects(): void
    {
        Mail::fake();

        $admin = $this->grantAdminRole(User::factory()->create());
        $pending = User::factory()->create([
            'is_active' => false,
            'invitation_token' => 'original-token',
            'invitation_sent_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.resendInvitation', $pending->id))
            ->assertRedirect();

        Mail::assertSent(UserInvitationMail::class, fn ($mail) => $mail->hasTo($pending->email));
    }

    public function test_resend_invitation_refreshes_the_token(): void
    {
        Mail::fake();

        $admin = $this->grantAdminRole(User::factory()->create());
        $pending = User::factory()->create([
            'is_active' => false,
            'invitation_token' => 'old-token',
            'invitation_sent_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.resendInvitation', $pending->id));

        $this->assertDatabaseMissing('users', ['id' => $pending->id, 'invitation_token' => 'old-token']);
    }

    public function test_unauthenticated_resend_is_redirected_to_login(): void
    {
        $pending = User::factory()->create(['is_active' => false, 'invitation_token' => 'tok']);

        $this->post(route('admin.users.resendInvitation', $pending->id))
            ->assertRedirect(route('login'));
    }
}
