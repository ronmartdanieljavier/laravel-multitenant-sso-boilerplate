<?php

namespace App\Admin\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantErrorLog;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantErrorLogWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    }

    private function errorLog(Tenant $tenant, array $overrides = []): TenantErrorLog
    {
        return TenantErrorLog::create(array_merge([
            'tenant_id' => $tenant->id,
            'error_code' => 'E-ACME-TESTTEST',
            'exception_class' => 'RuntimeException',
            'message' => 'Something broke',
            'severity' => 'error',
            'file' => '/app/foo.php',
            'line' => 42,
        ], $overrides));
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $tenant = $this->tenant();

        $this->get(route('admin.tenants.errors', $tenant))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_error_list(): void
    {
        $user = User::factory()->create();
        $tenant = $this->tenant();
        $this->errorLog($tenant);

        $this->actingAs($user)
            ->get(route('admin.tenants.errors', $tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/Errors')
                ->has('logs', 1)
                ->where('tenant.id', $tenant->id)
            );
    }

    public function test_error_list_only_shows_logs_for_the_given_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = $this->tenant();
        $tenant2 = Tenant::factory()->create(['slug' => 'other']);

        $this->errorLog($tenant1);
        TenantErrorLog::create([
            'tenant_id' => $tenant2->id,
            'error_code' => 'E-OTHER-XXXXXXXX',
            'exception_class' => 'Exception',
            'message' => 'Other tenant error',
            'severity' => 'error',
        ]);

        $this->actingAs($user)
            ->get(route('admin.tenants.errors', $tenant1))
            ->assertInertia(fn ($page) => $page->has('logs', 1));
    }

    public function test_error_detail_page_renders(): void
    {
        $user = User::factory()->create();
        $tenant = $this->tenant();
        $log = $this->errorLog($tenant);

        $this->actingAs($user)
            ->get(route('admin.tenants.errors.show', [$tenant, $log]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/ErrorDetail')
                ->where('log.error_code', 'E-ACME-TESTTEST')
            );
    }

    public function test_error_detail_returns_404_for_wrong_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = $this->tenant();
        $tenant2 = Tenant::factory()->create(['slug' => 'other']);
        $log = $this->errorLog($tenant2, ['error_code' => 'E-OTHER-YYYYYYYY']);

        $this->actingAs($user)
            ->get(route('admin.tenants.errors.show', [$tenant1, $log]))
            ->assertNotFound();
    }

    public function test_error_can_be_resolved(): void
    {
        $user = User::factory()->create();
        $tenant = $this->tenant();
        $log = $this->errorLog($tenant);

        $this->assertNull($log->resolved_at);

        $this->actingAs($user)
            ->patch(route('admin.tenants.errors.resolve', [$tenant, $log]))
            ->assertRedirect();

        $this->assertNotNull($log->fresh()->resolved_at);
    }

    public function test_error_can_be_unresolved(): void
    {
        $user = User::factory()->create();
        $tenant = $this->tenant();
        $log = $this->errorLog($tenant, ['resolved_at' => now()]);

        $this->actingAs($user)
            ->patch(route('admin.tenants.errors.unresolve', [$tenant, $log]))
            ->assertRedirect();

        $this->assertNull($log->fresh()->resolved_at);
    }

    public function test_error_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $tenant = $this->tenant();
        $log = $this->errorLog($tenant);

        $this->actingAs($user)
            ->delete(route('admin.tenants.errors.destroy', [$tenant, $log]))
            ->assertRedirect(route('admin.tenants.errors', $tenant));

        $this->assertDatabaseMissing('tenant_error_logs', ['id' => $log->id]);
    }

    public function test_unresolved_filter_excludes_resolved_errors(): void
    {
        $user = User::factory()->create();
        $tenant = $this->tenant();

        $this->errorLog($tenant, ['error_code' => 'E-ACME-OPEN0001']);
        $this->errorLog($tenant, ['error_code' => 'E-ACME-DONE0001', 'resolved_at' => now()]);

        $this->actingAs($user)
            ->get(route('admin.tenants.errors', $tenant).'?unresolved=1')
            ->assertInertia(fn ($page) => $page->has('logs', 1));
    }
}
