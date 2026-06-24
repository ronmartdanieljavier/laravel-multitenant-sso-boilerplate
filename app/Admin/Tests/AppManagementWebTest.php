<?php

namespace App\Admin\Tests;

use App\Models\Central\App;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AppManagementWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('admin.apps'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_apps_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.apps'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Apps/Index')
                ->has('apps')
            );
    }

    public function test_apps_page_lists_all_apps(): void
    {
        $user = User::factory()->create();
        $existing = App::count();
        App::factory()->count(3)->create();

        $this->actingAs($user)
            ->get(route('admin.apps'))
            ->assertInertia(fn ($page) => $page
                ->has('apps', $existing + 3)
            );
    }

    public function test_apps_payload_includes_expected_fields(): void
    {
        $user = User::factory()->create();
        App::factory()->create(['name' => 'My App', 'description' => 'A test app']);

        $this->actingAs($user)
            ->get(route('admin.apps'))
            ->assertInertia(fn ($page) => $page
                ->has('apps.0', fn ($app) => $app
                    ->has('id')
                    ->has('name')
                    ->has('slug')
                    ->has('description')
                    ->has('is_active')
                )
            );
    }

    public function test_unauthenticated_user_cannot_update_app(): void
    {
        $app = App::factory()->create();

        $this->put(route('admin.apps.update', $app), ['name' => 'New Name'])
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_update_app_name_and_description(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create(['name' => 'Old Name', 'description' => 'Old desc']);

        $this->actingAs($user)
            ->put(route('admin.apps.update', $app), [
                'name' => 'New Name',
                'description' => 'Updated description',
            ])
            ->assertRedirect(route('admin.apps'));

        $app->refresh();
        $this->assertSame('New Name', $app->name);
        $this->assertSame('Updated description', $app->description);
    }

    public function test_authenticated_user_can_clear_description(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create(['description' => 'Has a description']);

        $this->actingAs($user)
            ->put(route('admin.apps.update', $app), [
                'name' => $app->name,
                'description' => null,
            ])
            ->assertRedirect(route('admin.apps'));

        $this->assertNull($app->fresh()->description);
    }

    public function test_update_rejects_missing_name(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.apps.update', $app), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_update_rejects_name_over_255_chars(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.apps.update', $app), ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name');
    }

    public function test_update_rejects_description_over_1000_chars(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.apps.update', $app), [
                'name' => $app->name,
                'description' => str_repeat('a', 1001),
            ])
            ->assertSessionHasErrors('description');
    }

    public function test_update_returns_404_for_nonexistent_app(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.apps.update', 999), ['name' => 'Name'])
            ->assertNotFound();
    }

    public function test_update_does_not_change_slug_or_url(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create(['slug' => 'original-slug', 'url' => 'https://example.com']);

        $this->actingAs($user)
            ->put(route('admin.apps.update', $app), ['name' => 'Renamed App'])
            ->assertRedirect(route('admin.apps'));

        $app->refresh();
        $this->assertSame('original-slug', $app->slug);
        $this->assertSame('https://example.com', $app->url);
    }
}
