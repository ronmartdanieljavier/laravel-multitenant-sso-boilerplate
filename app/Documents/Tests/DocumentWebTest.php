<?php

namespace App\Documents\Tests;

use App\Admin\Services\TenantSettingsService;
use App\Auth\Enums\Role;
use App\Documents\Enums\DocumentSource;
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

        $this->mockSettingsService();

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

    /**
     * @param  string[]  $allowedTypes
     * @param  array<string, int>  $maxSizes
     */
    private function mockSettingsService(
        array $allowedTypes = ['pdf', 'doc', 'text', 'excel', 'image', 'csv'],
        array $maxSizes = ['pdf' => 5, 'doc' => 5, 'text' => 5, 'excel' => 5, 'image' => 5, 'csv' => 5],
    ): void {
        $settingsService = \Mockery::mock(TenantSettingsService::class);
        $settingsService->allows('resolveDisk')->andReturn($this->fakeDisk);
        $settingsService->allows('resolveUploadConstraints')->andReturn([
            'allowedTypes' => $allowedTypes,
            'maxSizes' => $maxSizes,
        ]);
        $this->app->instance(TenantSettingsService::class, $settingsService);
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
            'source' => DocumentSource::Upload->value,
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
                'source' => DocumentSource::Upload->value,
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

    public function test_index_page_passes_upload_constraints_prop(): void
    {
        $this->actingAs($this->user)
            ->get('/documents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Index')
                ->has('uploadConstraints')
                ->has('uploadConstraints.allowedTypes')
                ->has('uploadConstraints.maxSizes')
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

    public function test_store_rejects_disallowed_file_type(): void
    {
        $this->mockSettingsService(
            allowedTypes: ['pdf'],
            maxSizes: ['pdf' => 5],
        );

        $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'Wrong Type',
                'file' => UploadedFile::fake()->create('spreadsheet.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_store_rejects_file_exceeding_per_type_max_size(): void
    {
        $this->mockSettingsService(
            allowedTypes: ['pdf'],
            maxSizes: ['pdf' => 1],
        );

        // 1.5 MB — over the 1 MB limit
        $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'Oversized PDF',
                'file' => UploadedFile::fake()->create('big.pdf', 1536, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_store_accepts_allowed_file_type_within_size_limit(): void
    {
        $this->mockSettingsService(
            allowedTypes: ['image'],
            maxSizes: ['image' => 5],
        );

        $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'Profile Photo',
                'file' => UploadedFile::fake()->create('photo.png', 512, 'image/png'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('documents', ['title' => 'Profile Photo'], 'tenant');
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
            'source' => DocumentSource::Upload->value,
        ]);

        $this->actingAs($this->user)
            ->delete("/documents/{$document->id}")
            ->assertRedirect()
            ->assertSessionHas('success', 'Document deleted.');

        $this->assertDatabaseMissing('documents', ['id' => $document->id], 'tenant');
    }

    public function test_documents_include_source_field_in_response(): void
    {
        Document::on('tenant')->create([
            'title' => 'Report Doc',
            'description' => null,
            'file_path' => 'demo/reports/report.pdf',
            'file_name' => 'report.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => $this->user->id,
            'uploaded_by_name' => $this->user->name,
            'source' => DocumentSource::Report->value,
        ]);

        $this->actingAs($this->user)
            ->get('/documents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Index')
                ->where('documents.data.0.source', 'report')
            );
    }

    public function test_uploaded_document_source_defaults_to_upload(): void
    {
        $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'Sourced Upload',
                'file' => UploadedFile::fake()->create('doc.pdf', 128, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'title' => 'Sourced Upload',
            'source' => 'upload',
        ], 'tenant');
    }

    public function test_user_can_download_selected_documents_as_zip(): void
    {
        $this->fakeDisk->put('slug/documents/a.pdf', 'content-a');
        $this->fakeDisk->put('slug/documents/b.pdf', 'content-b');

        $docA = Document::on('tenant')->create([
            'title' => 'Doc A',
            'description' => null,
            'file_path' => 'slug/documents/a.pdf',
            'file_name' => 'a.pdf',
            'file_size' => 9,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => $this->user->id,
            'uploaded_by_name' => $this->user->name,
            'source' => DocumentSource::Upload->value,
        ]);

        $docB = Document::on('tenant')->create([
            'title' => 'Doc B',
            'description' => null,
            'file_path' => 'slug/documents/b.pdf',
            'file_name' => 'b.pdf',
            'file_size' => 9,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => $this->user->id,
            'uploaded_by_name' => $this->user->name,
            'source' => DocumentSource::Upload->value,
        ]);

        $response = $this->actingAs($this->user)
            ->post('/documents/download-zip', ['ids' => [$docA->id, $docB->id]]);

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=documents.zip');
    }

    public function test_download_zip_requires_ids(): void
    {
        $this->actingAs($this->user)
            ->post('/documents/download-zip', [])
            ->assertSessionHasErrors('ids');
    }

    public function test_guest_cannot_download_zip(): void
    {
        $this->post('/documents/download-zip', ['ids' => [1]])
            ->assertRedirect('/login');
    }
}
