<?php

namespace Tests\Feature\Repositories\Central;

use App\Data\Repositories\Central\UserRepositoryData;
use App\Data\Repositories\Central\UserWithPermissionsRepositoryData;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Repositories\Central\UserRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(UserRepository::class);
    }

    public function test_find_returns_user_repository_data(): void
    {
        $user = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $result = $this->repository->find($user->id);

        $this->assertInstanceOf(UserRepositoryData::class, $result);
        $this->assertSame($user->id, $result->id);
        $this->assertSame('Alice', $result->name);
        $this->assertSame('alice@example.com', $result->email);
    }

    public function test_list_with_permissions_returns_collection_of_dtos(): void
    {
        User::factory()->create();

        $results = $this->repository->listWithPermissions();

        $this->assertNotEmpty($results);
        $this->assertInstanceOf(UserWithPermissionsRepositoryData::class, $results->first());
    }

    public function test_list_with_permissions_embeds_app_and_tenant_data(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create(['name' => 'My App']);
        $tenant = Tenant::factory()->create();

        $user->userApps()->create(['app_id' => $app->id, 'role' => 'user']);
        $user->userAppTenants()->create(['app_id' => $app->id, 'tenant_id' => $tenant->id, 'role' => 'user', 'is_default' => true]);

        $results = $this->repository->listWithPermissions();
        $dto = $results->firstWhere('email', $user->email);

        $this->assertCount(1, $dto->apps);
        $this->assertSame($app->id, $dto->apps[0]->appId);
        $this->assertSame('My App', $dto->apps[0]->appName);
        $this->assertSame([$tenant->id], $dto->apps[0]->tenantIds);
    }

    public function test_find_with_permissions_returns_dto_with_apps(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();

        $user->userApps()->create(['app_id' => $app->id, 'role' => 'admin']);

        $dto = $this->repository->findWithPermissions($user->id);

        $this->assertInstanceOf(UserWithPermissionsRepositoryData::class, $dto);
        $this->assertSame($user->id, $dto->id);
        $this->assertCount(1, $dto->apps);
        $this->assertSame('admin', $dto->apps[0]->role);
    }

    public function test_find_by_invitation_token_returns_dto_for_inactive_user(): void
    {
        User::factory()->create([
            'is_active' => false,
            'invitation_token' => 'abc-token-123',
        ]);

        $dto = $this->repository->findByInvitationToken('abc-token-123');

        $this->assertInstanceOf(UserRepositoryData::class, $dto);
        $this->assertSame('abc-token-123', $dto->invitationToken);
        $this->assertFalse($dto->isActive);
    }

    public function test_find_by_invitation_token_returns_null_for_active_user(): void
    {
        User::factory()->create([
            'is_active' => true,
            'invitation_token' => 'xyz-token',
        ]);

        $this->assertNull($this->repository->findByInvitationToken('xyz-token'));
    }

    public function test_find_by_invitation_token_returns_null_for_unknown_token(): void
    {
        $this->assertNull($this->repository->findByInvitationToken('nonexistent'));
    }

    public function test_create_invited_returns_dto_with_invitation_fields(): void
    {
        $dto = $this->repository->createInvited('Bob', 'bob@example.com');

        $this->assertInstanceOf(UserRepositoryData::class, $dto);
        $this->assertSame('Bob', $dto->name);
        $this->assertSame('bob@example.com', $dto->email);
        $this->assertFalse($dto->isActive);
        $this->assertNotNull($dto->invitationToken);
        $this->assertNotNull($dto->invitationSentAt);

        $this->assertDatabaseHas('users', ['email' => 'bob@example.com', 'is_active' => false]);
    }

    public function test_update_profile_persists_changes(): void
    {
        $user = User::factory()->create(['name' => 'Old', 'email' => 'old@example.com']);

        $this->repository->updateProfile($user->id, 'New', 'new@example.com');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New', 'email' => 'new@example.com']);
    }

    public function test_activate_invitation_activates_user_and_clears_token(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'invitation_token' => 'tok-abc',
        ]);

        $this->repository->activateInvitation($user->id, 'Active Name', 'newpassword');

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->invitation_token);
        $this->assertSame('Active Name', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }
}
