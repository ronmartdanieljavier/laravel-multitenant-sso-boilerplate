<?php

namespace Tests\Feature\Repositories\Central;

use App\Admin\Data\UpdateAppData;
use App\Data\Repositories\Central\AppRepositoryData;
use App\Models\Central\App;
use App\Repositories\Central\AppRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AppRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AppRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(AppRepository::class);
    }

    public function test_list_ordered_returns_collection_of_app_repository_data(): void
    {
        App::factory()->create();

        $results = $this->repository->listOrdered();

        $this->assertNotEmpty($results);
        $this->assertInstanceOf(AppRepositoryData::class, $results->first());
    }

    public function test_list_ordered_dto_contains_all_fields(): void
    {
        $app = App::factory()->create([
            'name' => 'Test App',
            'slug' => 'test-app',
            'description' => 'A test app',
            'is_active' => true,
        ]);

        $result = $this->repository->listOrdered()->firstWhere('id', $app->id);

        $this->assertSame($app->id, $result->id);
        $this->assertSame('Test App', $result->name);
        $this->assertSame('test-app', $result->slug);
        $this->assertSame('A test app', $result->description);
        $this->assertTrue($result->isActive);
    }

    public function test_update_returns_app_repository_data_with_updated_values(): void
    {
        $app = App::factory()->create(['name' => 'Old Name', 'description' => 'Old desc']);

        $data = new UpdateAppData(name: 'New Name', description: 'New desc');
        $result = $this->repository->update($app->id, $data);

        $this->assertInstanceOf(AppRepositoryData::class, $result);
        $this->assertSame('New Name', $result->name);
        $this->assertSame('New desc', $result->description);
        $this->assertDatabaseHas('apps', ['id' => $app->id, 'name' => 'New Name']);
    }

    public function test_update_persists_null_description(): void
    {
        $app = App::factory()->create(['name' => 'App', 'description' => 'Something']);

        $result = $this->repository->update($app->id, new UpdateAppData(name: 'App', description: null));

        $this->assertNull($result->description);
        $this->assertDatabaseHas('apps', ['id' => $app->id, 'description' => null]);
    }
}
