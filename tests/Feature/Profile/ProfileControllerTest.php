<?php

namespace Tests\Feature\Profile;

use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_profile(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_profile_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Profile/Index'));
    }

    public function test_user_can_update_display_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)
            ->put('/profile/name', ['name' => 'New Name'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Display name updated.');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_update_name_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profile/name', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_update_name_enforces_max_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profile/name', ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_upload_profile_picture(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->actingAs($user)
            ->post('/profile/picture', ['profile_picture' => $file])
            ->assertRedirect()
            ->assertSessionHas('success', 'Profile picture updated.');

        $user->refresh();
        $this->assertNotNull($user->profile_picture);
        Storage::disk('local')->assertExists($user->profile_picture);
    }

    public function test_uploading_new_picture_deletes_old_one(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $first = UploadedFile::fake()->image('first.jpg');
        $this->actingAs($user)->post('/profile/picture', ['profile_picture' => $first]);
        $user->refresh();
        $oldPath = $user->profile_picture;

        $second = UploadedFile::fake()->image('second.jpg');
        $this->actingAs($user)->post('/profile/picture', ['profile_picture' => $second]);

        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_profile_picture_must_be_an_image(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post('/profile/picture', ['profile_picture' => $file])
            ->assertSessionHasErrors('profile_picture');
    }

    public function test_profile_picture_must_not_exceed_2mb(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('large.jpg')->size(3000);

        $this->actingAs($user)
            ->post('/profile/picture', ['profile_picture' => $file])
            ->assertSessionHasErrors('profile_picture');
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'old-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Password updated.');

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_password_update_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_password_update_requires_confirmation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'old-password',
                'password' => 'new-password123',
                'password_confirmation' => 'does-not-match',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_new_password_must_be_at_least_8_characters(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'old-password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');
    }
}
