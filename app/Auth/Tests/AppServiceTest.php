<?php

namespace App\Auth\Tests;

use App\Auth\Data\AppAccessData;
use App\Auth\Enums\Role;
use App\Auth\Services\AppService;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\LaravelData\DataCollection;
use Tests\TestCase;

class AppServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AppService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AppService::class);
    }

    public function test_load_apps_returns_empty_collection_when_user_has_no_apps(): void
    {
        $user = User::factory()->create();

        $result = $this->service->loadApps($user->id);

        $this->assertInstanceOf(DataCollection::class, $result);
        $this->assertCount(0, $result->toCollection());
    }

    public function test_load_apps_returns_apps_with_correct_data(): void
    {
        $user = User::factory()->create();
        $app = App::where('slug', 'admin')->first();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::Admin]);

        $result = $this->service->loadApps($user->id);

        $apps = $result->toCollection();
        $this->assertCount(1, $apps);
        $this->assertInstanceOf(AppAccessData::class, $apps->first());
        $this->assertSame($app->id, $apps->first()->appId);
        $this->assertSame('admin', $apps->first()->slug);
        $this->assertSame(Role::Admin, $apps->first()->role);
    }

    public function test_load_apps_includes_tenant_access(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->userApps()->create(['app_id' => $app->id, 'role' => Role::User]);
        $user->userAppTenants()->create([
            'app_id' => $app->id,
            'tenant_id' => $tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $result = $this->service->loadApps($user->id);

        $tenants = $result->toCollection()->first()->tenants->toCollection();
        $this->assertCount(1, $tenants);
        $this->assertSame($tenant->id, $tenants->first()->tenantId);
        $this->assertSame($tenant->slug, $tenants->first()->slug);
        $this->assertTrue($tenants->first()->isDefault);
    }

    public function test_load_apps_only_returns_apps_for_the_given_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $appA = App::factory()->create();
        $appB = App::factory()->create();
        $userA->userApps()->create(['app_id' => $appA->id, 'role' => Role::User]);
        $userB->userApps()->create(['app_id' => $appB->id, 'role' => Role::User]);

        $result = $this->service->loadApps($userA->id);

        $apps = $result->toCollection();
        $this->assertCount(1, $apps);
        $this->assertSame($appA->id, $apps->first()->appId);
    }

    public function test_load_apps_returns_multiple_apps(): void
    {
        $user = User::factory()->create();
        $appA = App::where('slug', 'admin')->first();
        $appB = App::where('slug', 'tenant')->first();
        $user->userApps()->create(['app_id' => $appA->id, 'role' => Role::Admin]);
        $user->userApps()->create(['app_id' => $appB->id, 'role' => Role::User]);

        $result = $this->service->loadApps($user->id);

        $this->assertCount(2, $result->toCollection());
    }
}
