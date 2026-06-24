<?php

namespace App\Admin\Tests;

use App\Admin\Data\InviteUserData;
use App\Admin\Data\UpdateUserData;
use App\Admin\Data\UserAppPermissionData;
use App\Admin\Data\UserData;
use App\Admin\Services\UserManagementService;
use App\Models\Central\App;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Spatie\LaravelData\DataCollection;
use Tests\TestCase;

class UserManagementServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserManagementService::class);
    }

    public function test_list_returns_collection_of_user_data(): void
    {
        User::factory()->create();

        $result = $this->service->list();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertNotEmpty($result);
        $this->assertInstanceOf(UserData::class, $result->first());
    }

    public function test_list_user_data_contains_expected_fields(): void
    {
        User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com', 'is_active' => true]);

        $alice = $this->service->list()->firstWhere('email', 'alice@example.com');

        $this->assertNotNull($alice);
        $this->assertSame('Alice', $alice->name);
        $this->assertTrue($alice->isActive);
        $this->assertEmpty($alice->apps);
    }

    public function test_invite_returns_user_data(): void
    {
        Mail::fake();
        $app = App::factory()->create();

        $result = $this->service->invite(new InviteUserData(
            name: 'Bob',
            email: 'bob@example.com',
            apps: new DataCollection(UserAppPermissionData::class, [
                new UserAppPermissionData(appId: $app->id, role: 'user', tenantIds: []),
            ]),
        ));

        $this->assertInstanceOf(UserData::class, $result);
        $this->assertSame('bob@example.com', $result->email);
        $this->assertFalse($result->isActive);
    }

    public function test_update_returns_user_data(): void
    {
        $user = User::factory()->create(['name' => 'Old', 'email' => 'old@example.com']);

        $result = $this->service->update($user->id, new UpdateUserData(
            name: 'New',
            email: 'new@example.com',
            apps: new DataCollection(UserAppPermissionData::class, []),
        ));

        $this->assertInstanceOf(UserData::class, $result);
        $this->assertSame('New', $result->name);
        $this->assertSame('new@example.com', $result->email);
    }

    public function test_accept_invitation_returns_user_data(): void
    {
        $user = User::factory()->create(['is_active' => false, 'invitation_token' => 'abc']);

        $result = $this->service->acceptInvitation($user->id, 'Activated Name', 'newpassword');

        $this->assertInstanceOf(UserData::class, $result);
        $this->assertTrue($result->isActive);
        $this->assertSame('Activated Name', $result->name);
    }
}
