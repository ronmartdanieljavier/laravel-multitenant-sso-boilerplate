<?php

namespace App\Documents\Tests;

use App\Admin\Services\TenantSettingsService;
use App\Auth\Enums\Role;
use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Document;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithFakeTenantContext;
use Tests\TestCase;

class DocumentWebTest extends TestCase
{
    use LazilyRefreshDatabase, WithFakeTenantContext;

    private App $tenantApp;

    private Tenant $tenant;

    private User $user;

    private Filesystem $fakeDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
        ]);

        Storage::fake('local');
        $this->fakeDisk = Storage::disk('local');

        $settingsService = \Mockery::mock(TenantSettingsService::class);
        $settingsService->allows('resolveDisk')->andReturn($this->fakeDisk);
        $this->app->instance(TenantSettingsService::class, $settingsService);

        $this->tenantApp = App::where('slug', 'tenant')->first() ?? App::factory()->create(['slug' => 'tenant', 'is_active' => true]);
        $this->tenant = Tenant::factory()->create(['is_active' => true, 'is_maintenance' => false]);
        $this->user = User::factory()->create();

        $this->user->userApps()->firstOrCreate(['app_id' => $this->tenantApp->id], ['role' => Role::User]);
        $this->user->userAppTenants()->create([
            'app_id' => $this->tenantApp->id,
            'tenant_id' => $this->tenant->id,
            'role' => Role::User,
            'is_default' => true,
        ]);

        $this->setFakeTenant($this->tenant);
    }

    public function test_guest_is_redirected_from_documents(): void
    {
        $this->get('/documents')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_documents_page(): void
    {
        $this->actingAs($this->user)
            ->get('/documents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Documents/Index'));
    }

    public function test_documents_page_passes_documents_and_tenant_props(): void
    {
        Document::on('tenant')->create([
            'title' => 'My Doc',
            'description' => null,
            'file_path' => 'documents/test/file.pdf',
            'file_name' => 'file.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => $this->user->id,
            'uploaded_by_name' => $this->user->name,
        ]);

        $this->actingAs($this->user)
            ->get('/documents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Index')
                ->has('documents.data', 1)
                ->has('documents.total')
                ->has('tenant')
            );
    }

    public function test_documents_page_is_paginated(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            Document::on('tenant')->create([
                'title' => "Doc {$i}",
                'description' => null,
                'file_path' => "documents/test/file{$i}.pdf",
                'file_name' => "file{$i}.pdf",
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
                'uploaded_by_user_id' => $this->user->id,
                'uploaded_by_name' => $this->user->name,
            ]);
        }

        $this->actingAs($this->user)
            ->get('/documents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Index')
                ->where('documents.total', 25)
                ->where('documents.per_page', 20)
                ->has('documents.data', 20)
            );
    }

    public function test_user_can_upload_a_document(): void
    {
        $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'Test Upload',
                'file' => UploadedFile::fake()->create('report.pdf', 512, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Document uploaded successfully.');

        $this->assertDatabaseHas('documents', ['title' => 'Test Upload'], 'tenant');
    }

    public function test_store_requires_title(): void
    {
        $this->actingAs($this->user)
            ->post('/documents', [
                'file' => UploadedFile::fake()->create('file.pdf', 100),
            ])
            ->assertSessionHasErrors('title');
    }

    public function test_store_requires_file(): void
    {
        $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'No File',
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_user_can_delete_a_document(): void
    {
        Storage::fake('public');

        $document = Document::on('tenant')->create([
            'title' => 'To Delete',
            'description' => null,
            'file_path' => 'documents/test/delete-me.pdf',
            'file_name' => 'delete-me.pdf',
            'file_size' => 512,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => $this->user->id,
            'uploaded_by_name' => $this->user->name,
        ]);

        $this->actingAs($this->user)
            ->delete("/documents/{$document->id}")
            ->assertRedirect()
            ->assertSessionHas('success', 'Document deleted.');

        $this->assertDatabaseMissing('documents', ['id' => $document->id], 'tenant');
    }
}
