<?php

namespace App\Profile\Tests;

use App\Models\Central\User;
use App\Profile\Data\ProfileData;
use App\Profile\Services\ProfileService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProfileService::class);
    }

    public function test_get_profile_returns_profile_data_for_user(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $profile = $this->service->getProfile($user->id);

        $this->assertInstanceOf(ProfileData::class, $profile);
        $this->assertSame($user->id, $profile->id);
        $this->assertSame('Jane Doe', $profile->name);
        $this->assertSame('jane@example.com', $profile->email);
    }

    public function test_update_name_persists_and_returns_updated_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $profile = $this->service->updateName($user->id, 'New Name');

        $this->assertSame('New Name', $profile->name);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_update_picture_persists_and_returns_updated_profile(): void
    {
        $user = User::factory()->create();

        $profile = $this->service->updatePicture($user->id, 'profile-pictures/avatar.jpg');

        $this->assertSame('profile-pictures/avatar.jpg', $profile->profilePicture);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'profile_picture' => 'profile-pictures/avatar.jpg']);
    }

    public function test_update_password_hashes_and_persists_new_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->service->updatePassword($user->id, 'new-password123');

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
        $this->assertFalse(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_get_profile_throws_when_user_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getProfile(99999);
    }
}
