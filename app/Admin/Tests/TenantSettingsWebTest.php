<?php

namespace App\Admin\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantSetting;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantSettingsWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $tenant = $this->tenant();

        $this->get(route('admin.tenants.settings', $tenant))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_tenant_settings_page(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $this->actingAs($user)
            ->get(route('admin.tenants.settings', $tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/Settings')
                ->has('settings')
                ->has('redisConnections')
                ->where('tenant.id', $tenant->id)
            );
    }

    public function test_settings_show_null_when_no_overrides_exist(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $this->actingAs($user)
            ->get(route('admin.tenants.settings', $tenant))
            ->assertInertia(fn ($page) => $page
                ->has('settings', fn ($s) => $s
                    ->where('email_driver', null)
                    ->where('storage_driver', null)
                    ->etc()
                )
            );
    }

    public function test_authenticated_user_can_update_tenant_settings(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $this->actingAs($user)
            ->put(route('admin.tenants.settings.update', $tenant), [
                'email_driver' => 'postmark',
                'postmark_token' => 'token-123',
                'postmark_from_address' => 'noreply@acme.com',
            ])
            ->assertRedirect(route('admin.tenants.settings', $tenant));

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'email_driver',
            'value' => 'postmark',
        ]);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'postmark_token',
            'value' => 'token-123',
        ]);
    }

    public function test_clearing_a_setting_removes_the_row(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        TenantSetting::create(['tenant_id' => $tenant->id, 'key' => 'email_driver', 'value' => 'postmark']);

        $this->actingAs($user)
            ->put(route('admin.tenants.settings.update', $tenant), [
                'email_driver' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'email_driver',
        ]);
    }

    public function test_logo_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $file = UploadedFile::fake()->image('logo.png', 200, 50);

        $this->actingAs($user)
            ->post(route('admin.tenants.settings.logo', $tenant), ['logo' => $file])
            ->assertRedirect(route('admin.tenants.settings', $tenant));

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'report_logo_path',
        ]);
    }

    public function test_logo_upload_requires_an_image(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $this->actingAs($user)
            ->post(route('admin.tenants.settings.logo', $tenant), ['logo' => $file])
            ->assertSessionHasErrors('logo');
    }

    public function test_logo_can_be_deleted(): void
    {
        Storage::fake('public');

        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $path = 'tenant-logos/1/logo.png';
        Storage::disk('public')->put($path, 'fake-image');
        TenantSetting::create(['tenant_id' => $tenant->id, 'key' => 'report_logo_path', 'value' => $path]);

        $this->actingAs($user)
            ->delete(route('admin.tenants.settings.logo.delete', $tenant))
            ->assertRedirect(route('admin.tenants.settings', $tenant));

        $this->assertDatabaseMissing('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'report_logo_path',
        ]);
    }

    public function test_invalid_email_driver_is_rejected(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $this->actingAs($user)
            ->put(route('admin.tenants.settings.update', $tenant), [
                'email_driver' => 'invalid-driver',
            ])
            ->assertSessionHasErrors('email_driver');
    }

    public function test_redis_connections_prop_contains_only_redis_driver_keys(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $response = $this->actingAs($user)
            ->get(route('admin.tenants.settings', $tenant))
            ->assertOk();

        $redisConnections = $response->viewData('page')['props']['redisConnections'] ?? null;

        $this->assertNotNull($redisConnections);
        $this->assertIsArray($redisConnections);
        $this->assertContains('redis', $redisConnections);
    }

    public function test_report_server_settings_can_be_saved(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $this->actingAs($user)
            ->put(route('admin.tenants.settings.update', $tenant), [
                'report_queue' => 'heavy',
                'report_timeout' => 120,
                'report_connection' => 'redis',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_settings', ['tenant_id' => $tenant->id, 'key' => 'report_queue', 'value' => 'heavy']);
        $this->assertDatabaseHas('tenant_settings', ['tenant_id' => $tenant->id, 'key' => 'report_timeout', 'value' => '120']);
        $this->assertDatabaseHas('tenant_settings', ['tenant_id' => $tenant->id, 'key' => 'report_connection', 'value' => 'redis']);
    }

    public function test_tenant_users_page_lists_users_in_tenant(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $this->actingAs($user)
            ->get(route('admin.tenants.users', $tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/Users')
                ->has('users')
                ->where('tenant.id', $tenant->id)
            );
    }

    public function test_upload_settings_appear_in_tenant_settings_payload(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $this->actingAs($user)
            ->get(route('admin.tenants.settings', $tenant))
            ->assertInertia(fn ($page) => $page
                ->has('settings', fn ($s) => $s
                    ->has('upload_allowed_types')
                    ->has('upload_max_size_pdf')
                    ->has('upload_max_size_doc')
                    ->has('upload_max_size_text')
                    ->has('upload_max_size_excel')
                    ->has('upload_max_size_image')
                    ->has('upload_max_size_csv')
                    ->has('effective_upload_allowed_types')
                    ->has('effective_upload_max_size_pdf')
                    ->etc()
                )
            );
    }

    public function test_tenant_upload_settings_can_be_saved(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $this->actingAs($user)
            ->put(route('admin.tenants.settings.update', $tenant), [
                'upload_allowed_types' => 'pdf,csv',
                'upload_max_size_pdf' => 8,
                'upload_max_size_csv' => 2,
            ])
            ->assertRedirect(route('admin.tenants.settings', $tenant));

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'upload_allowed_types',
            'value' => 'pdf,csv',
        ]);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'upload_max_size_pdf',
            'value' => '8',
        ]);
    }

    public function test_tenant_upload_max_size_above_100_is_rejected(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        $this->actingAs($user)
            ->put(route('admin.tenants.settings.update', $tenant), [
                'upload_max_size_excel' => 101,
            ])
            ->assertSessionHasErrors('upload_max_size_excel');
    }

    public function test_clearing_tenant_upload_settings_removes_rows(): void
    {
        $user = $this->grantAdminRole(User::factory()->create());
        $tenant = $this->tenant();

        TenantSetting::create(['tenant_id' => $tenant->id, 'key' => 'upload_allowed_types', 'value' => 'pdf']);

        $this->actingAs($user)
            ->put(route('admin.tenants.settings.update', $tenant), [
                'upload_allowed_types' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'upload_allowed_types',
        ]);
    }
}
