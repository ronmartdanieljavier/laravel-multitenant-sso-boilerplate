<?php

namespace App\Admin\Tests;

use App\Models\Central\App;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppManagementApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/admin/apps')->assertUnauthorized();
    }

    public function test_unauthenticated_put_returns_401(): void
    {
        $app = App::factory()->create();

        $this->putJson("/api/v1/admin/apps/{$app->id}", ['name' => 'Name'])->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_apps(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        App::factory()->count(2)->create();

        $this->getJson('/api/v1/admin/apps')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'description', 'is_active'],
                ],
            ]);
    }

    public function test_authenticated_user_can_update_app(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        $app = App::factory()->create(['name' => 'Old Name', 'description' => 'Old desc']);

        $this->putJson("/api/v1/admin/apps/{$app->id}", [
            'name' => 'New Name',
            'description' => 'New desc',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.description', 'New desc');

        $app->refresh();
        $this->assertSame('New Name', $app->name);
        $this->assertSame('New desc', $app->description);
    }

    public function test_authenticated_user_can_clear_description(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        $app = App::factory()->create(['description' => 'Has one']);

        $this->putJson("/api/v1/admin/apps/{$app->id}", [
            'name' => $app->name,
            'description' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.description', null);

        $this->assertNull($app->fresh()->description);
    }

    public function test_update_rejects_missing_name(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        $app = App::factory()->create();

        $this->putJson("/api/v1/admin/apps/{$app->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_update_rejects_name_over_255_chars(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        $app = App::factory()->create();

        $this->putJson("/api/v1/admin/apps/{$app->id}", ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_update_rejects_description_over_1000_chars(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        $app = App::factory()->create();

        $this->putJson("/api/v1/admin/apps/{$app->id}", [
            'name' => $app->name,
            'description' => str_repeat('a', 1001),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_update_returns_404_for_nonexistent_app(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/admin/apps/999', ['name' => 'Name'])->assertNotFound();
    }

    public function test_update_does_not_change_slug_or_url(): void
    {
        Sanctum::actingAs($this->grantAdminRole(User::factory()->create()));
        $app = App::factory()->create(['slug' => 'original-slug', 'url' => 'https://example.com']);

        $this->putJson("/api/v1/admin/apps/{$app->id}", ['name' => 'Renamed'])->assertOk();

        $app->refresh();
        $this->assertSame('original-slug', $app->slug);
        $this->assertSame('https://example.com', $app->url);
    }
}
