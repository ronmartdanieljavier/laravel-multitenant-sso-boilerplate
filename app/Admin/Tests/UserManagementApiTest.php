<?php

namespace App\Admin\Tests;

use App\Admin\Mail\UserInvitationMail;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_users(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        User::factory()->count(2)->create();

        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'is_active', 'apps'],
                ],
            ]);
    }

    public function test_authenticated_user_can_invite_user(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        $app = App::factory()->create();

        $this->postJson('/api/v1/admin/users/invite', [
            'name' => 'Invitee',
            'email' => 'invitee@example.com',
            'apps' => [['app_id' => $app->id, 'role' => 'user', 'tenant_ids' => []]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'invitee@example.com');

        $this->assertDatabaseHas('users', ['email' => 'invitee@example.com', 'is_active' => false]);
        Mail::assertSent(UserInvitationMail::class);
    }

    public function test_invite_rejects_duplicate_email(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        User::factory()->create(['email' => 'existing@example.com']);

        $this->postJson('/api/v1/admin/users/invite', [
            'name' => 'Dup',
            'email' => 'existing@example.com',
        ])->assertUnprocessable();
    }

    public function test_authenticated_user_can_update_user(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        $target = User::factory()->create(['name' => 'Before', 'email' => 'before@example.com']);
        $app = App::factory()->create();

        $this->putJson("/api/v1/admin/users/{$target->id}", [
            'name' => 'After',
            'email' => 'after@example.com',
            'apps' => [['app_id' => $app->id, 'role' => 'user', 'tenant_ids' => []]],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'After');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'After']);
    }

    public function test_update_assigns_tenant_permissions(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        $target = User::factory()->create();
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->putJson("/api/v1/admin/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'apps' => [['app_id' => $app->id, 'role' => 'user', 'tenant_ids' => [$tenant->id]]],
        ])->assertOk();

        $this->assertDatabaseHas('user_app_tenants', [
            'user_id' => $target->id,
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_update_rejects_missing_name(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        $target = User::factory()->create();

        $this->putJson("/api/v1/admin/users/{$target->id}", ['email' => 'x@x.com'])
            ->assertUnprocessable();
    }

    public function test_unauthenticated_invite_returns_401(): void
    {
        $this->postJson('/api/v1/admin/users/invite', ['name' => 'X', 'email' => 'x@x.com'])
            ->assertUnauthorized();
    }

    public function test_unauthenticated_update_returns_401(): void
    {
        $target = User::factory()->create();

        $this->putJson("/api/v1/admin/users/{$target->id}", ['name' => 'X', 'email' => 'x@x.com'])
            ->assertUnauthorized();
    }

    public function test_invite_response_contains_is_active_false(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));

        $this->postJson('/api/v1/admin/users/invite', [
            'name' => 'New Invitee',
            'email' => 'newinvitee@example.com',
            'apps' => [],
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_list_response_includes_invitation_sent_at_and_profile_picture_url(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        User::factory()->create();

        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'is_active', 'apps', 'invitation_sent_at', 'profile_picture_url'],
                ],
            ]);
    }

    public function test_resend_invitation_returns_success_message(): void
    {
        Mail::fake();

        $actor = $this->grantAdminRole(User::factory()->create());
        $pending = User::factory()->create([
            'is_active' => false,
            'invitation_token' => 'tok',
            'invitation_sent_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/admin/users/{$pending->id}/resend-invitation")
            ->assertOk()
            ->assertJsonPath('message', 'Invitation resent.');
    }

    public function test_resend_invitation_sends_email(): void
    {
        Mail::fake();

        $actor = $this->grantAdminRole(User::factory()->create());
        $pending = User::factory()->create([
            'is_active' => false,
            'invitation_token' => 'tok2',
            'invitation_sent_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/admin/users/{$pending->id}/resend-invitation");

        Mail::assertSent(UserInvitationMail::class, fn ($mail) => $mail->hasTo($pending->email));
    }

    public function test_unauthenticated_resend_is_rejected(): void
    {
        $pending = User::factory()->create(['is_active' => false, 'invitation_token' => 'tok3']);

        $this->postJson("/api/v1/admin/users/{$pending->id}/resend-invitation")
            ->assertUnauthorized();
    }
}
