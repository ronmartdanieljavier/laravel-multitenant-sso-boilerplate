<?php

namespace App\Profile\Tests;

use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_request_to_profile_is_rejected(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }

    public function test_authenticated_user_can_get_their_profile(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('name', 'Jane Doe')
            ->assertJsonPath('email', 'jane@example.com')
            ->assertJsonStructure(['id', 'name', 'email']);
    }

    public function test_user_can_update_name_via_api(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/name', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_update_name_via_api_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/name', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_update_name_via_api_rejects_empty_string(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/name', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_update_name_via_api_enforces_max_255_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/name', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_user_can_upload_profile_picture_via_api(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/picture', ['profile_picture' => UploadedFile::fake()->image('avatar.jpg')])
            ->assertCreated()
            ->assertJsonStructure(['id', 'name', 'email']);

        $user->refresh();
        $this->assertNotNull($user->profile_picture);
        Storage::disk('local')->assertExists($user->profile_picture);
    }

    public function test_uploading_new_picture_via_api_deletes_old_one(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/picture', ['profile_picture' => UploadedFile::fake()->image('first.jpg')])
            ->assertCreated();
        $user->refresh();
        $oldPath = $user->profile_picture;

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/picture', ['profile_picture' => UploadedFile::fake()->image('second.jpg')])
            ->assertCreated();

        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_picture_upload_via_api_rejects_non_image_files(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/picture', ['profile_picture' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_picture');
    }

    public function test_picture_upload_via_api_rejects_files_over_2mb(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/picture', ['profile_picture' => UploadedFile::fake()->image('large.jpg')->size(3000)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_picture');
    }

    public function test_picture_upload_via_api_requires_a_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/picture', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_picture');
    }

    public function test_user_can_change_password_via_api(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'old-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])
            ->assertOk()
            ->assertJson(['message' => 'Password updated successfully.']);

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_password_change_via_api_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
    }

    public function test_password_change_via_api_requires_confirmation_to_match(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'old-password',
                'password' => 'new-password123',
                'password_confirmation' => 'does-not-match',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_new_password_via_api_must_be_at_least_8_characters(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'old-password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
