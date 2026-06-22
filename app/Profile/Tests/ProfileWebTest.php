<?php

namespace App\Profile\Tests;

use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_profile_page(): void
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

    public function test_update_name_requires_name_field(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profile/name', [])
            ->assertSessionHasErrors('name');
    }

    public function test_update_name_rejects_empty_string(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profile/name', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_update_name_enforces_max_255_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profile/name', ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_upload_profile_picture(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/picture', ['profile_picture' => UploadedFile::fake()->image('avatar.jpg')])
            ->assertRedirect()
            ->assertSessionHas('success', 'Profile picture updated.');

        $user->refresh();
        $this->assertNotNull($user->profile_picture);
        Storage::disk('public')->assertExists($user->profile_picture);
    }

    public function test_uploading_new_picture_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/picture', ['profile_picture' => UploadedFile::fake()->image('first.jpg')]);
        $user->refresh();
        $oldPath = $user->profile_picture;

        $this->actingAs($user)->post('/profile/picture', ['profile_picture' => UploadedFile::fake()->image('second.jpg')]);

        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_profile_picture_must_be_an_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/picture', ['profile_picture' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('profile_picture');
    }

    public function test_profile_picture_must_not_exceed_2mb(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/picture', ['profile_picture' => UploadedFile::fake()->image('large.jpg')->size(3000)])
            ->assertSessionHasErrors('profile_picture');
    }

    public function test_profile_picture_field_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/picture', [])
            ->assertSessionHasErrors('profile_picture');
    }

    public function test_user_can_change_password(): void
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

    public function test_password_change_requires_correct_current_password(): void
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

    public function test_password_change_requires_confirmation_to_match(): void
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

    public function test_guest_cannot_update_name(): void
    {
        $this->put('/profile/name', ['name' => 'Hacker'])->assertRedirect('/login');
    }

    public function test_guest_cannot_upload_picture(): void
    {
        $this->post('/profile/picture', [])->assertRedirect('/login');
    }

    public function test_guest_cannot_change_password(): void
    {
        $this->put('/profile/password', [])->assertRedirect('/login');
    }
}
