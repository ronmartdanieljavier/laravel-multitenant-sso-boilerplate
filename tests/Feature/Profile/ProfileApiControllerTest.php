<?php

namespace Tests\Feature\Profile;

use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileApiControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'email']);
    }

    public function test_user_can_update_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/name', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_update_name_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/name', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_update_name_enforces_max_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/name', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_user_can_upload_profile_picture(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/picture', ['profile_picture' => $file])
            ->assertCreated()
            ->assertJsonStructure(['id', 'name', 'email']);

        $user->refresh();
        $this->assertNotNull($user->profile_picture);
        Storage::disk('public')->assertExists($user->profile_picture);
    }

    public function test_uploading_new_picture_deletes_old_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $first = UploadedFile::fake()->image('first.jpg');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/profile/picture', ['profile_picture' => $first])->assertCreated();
        $user->refresh();
        $oldPath = $user->profile_picture;

        $second = UploadedFile::fake()->image('second.jpg');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/profile/picture', ['profile_picture' => $second])->assertCreated();

        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_profile_picture_must_be_an_image(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/picture', ['profile_picture' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_picture');
    }

    public function test_profile_picture_must_not_exceed_2mb(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('large.jpg')->size(3000);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/picture', ['profile_picture' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_picture');
    }

    public function test_profile_picture_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/picture', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_picture');
    }

    public function test_user_can_update_password(): void
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

    public function test_password_update_requires_correct_current_password(): void
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

    public function test_password_update_requires_confirmation(): void
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

    public function test_new_password_must_be_at_least_8_characters(): void
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
