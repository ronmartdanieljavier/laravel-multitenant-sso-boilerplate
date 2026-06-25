<?php

namespace App\Admin\Tests;

use App\Models\Central\Tenant;
use App\Models\Central\TenantSetting;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantSettingsApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $tenant = $this->tenant();

        $this->getJson("/api/v1/admin/tenants/{$tenant->id}/settings")->assertUnauthorized();
    }

    public function test_authenticated_user_can_retrieve_tenant_settings(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $tenant = $this->tenant();

        $this->getJson("/api/v1/admin/tenants/{$tenant->id}/settings")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'email_driver',
                    'storage_driver',
                    'report_pdf_header_text',
                    'report_pdf_footer_text',
                    'report_logo_path',
                    'report_queue',
                    'report_timeout',
                    'report_connection',
                    'effective_email_driver',
                    'effective_storage_driver',
                ],
                'redis_connections',
            ]);
    }

    public function test_authenticated_user_can_update_tenant_settings(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $tenant = $this->tenant();

        $this->putJson("/api/v1/admin/tenants/{$tenant->id}/settings", [
            'email_driver' => 'mailgun',
            'mailgun_domain' => 'mg.acme.com',
            'mailgun_secret' => 'key-secret',
            'report_pdf_header_text' => 'Acme Corp',
            'report_pdf_footer_text' => 'Page {page}',
            'report_queue' => 'tenant-reports',
            'report_timeout' => 120,
        ])
            ->assertOk()
            ->assertJsonPath('data.email_driver', 'mailgun');

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'report_pdf_header_text',
            'value' => 'Acme Corp',
        ]);
    }

    public function test_tenant_settings_do_not_leak_between_tenants(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $tenant1 = Tenant::factory()->create(['slug' => 't1']);
        $tenant2 = Tenant::factory()->create(['slug' => 't2']);

        TenantSetting::create(['tenant_id' => $tenant1->id, 'key' => 'email_driver', 'value' => 'postmark']);

        $this->getJson("/api/v1/admin/tenants/{$tenant2->id}/settings")
            ->assertOk()
            ->assertJsonPath('data.email_driver', null);
    }

    public function test_logo_can_be_uploaded_via_api(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());
        $tenant = $this->tenant();

        $file = UploadedFile::fake()->image('logo.png', 200, 50);

        $this->postJson("/api/v1/admin/tenants/{$tenant->id}/settings/logo", ['logo' => $file])
            ->assertOk()
            ->assertJsonStructure(['data' => ['report_logo_path']]);
    }

    public function test_logo_can_be_deleted_via_api(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());
        $tenant = $this->tenant();

        $path = "tenant-logos/{$tenant->id}/logo.png";
        Storage::disk('public')->put($path, 'fake');
        TenantSetting::create(['tenant_id' => $tenant->id, 'key' => 'report_logo_path', 'value' => $path]);

        $this->deleteJson("/api/v1/admin/tenants/{$tenant->id}/settings/logo")
            ->assertOk()
            ->assertJson(['message' => 'Logo removed.']);

        $this->assertDatabaseMissing('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'report_logo_path',
        ]);
    }

    public function test_report_connection_can_be_saved_via_api(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $tenant = $this->tenant();

        $this->putJson("/api/v1/admin/tenants/{$tenant->id}/settings", [
            'report_queue' => 'heavy',
            'report_timeout' => 300,
            'report_connection' => 'redis',
        ])
            ->assertOk()
            ->assertJsonPath('data.report_connection', 'redis');

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'report_connection',
            'value' => 'redis',
        ]);
    }

    public function test_tenant_users_can_be_listed_via_api(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $tenant = $this->tenant();

        $this->getJson("/api/v1/admin/tenants/{$tenant->id}/users")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
