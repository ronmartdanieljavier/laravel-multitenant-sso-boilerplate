<?php

namespace App\Documents\Tests;

use App\Admin\Services\TenantSettingsService;
use App\Documents\Data\DocumentData;
use App\Documents\Services\DocumentService;
use App\Models\Central\User;
use App\Models\Tenant\Document;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DocumentService $service;

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

        $this->service = app(DocumentService::class);
    }

    public function test_paginate_returns_paginator_of_document_data(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Document::on('tenant')->create([
                'title' => "Doc {$i}",
                'description' => null,
                'file_path' => "documents/t/file{$i}.pdf",
                'file_name' => "file{$i}.pdf",
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
                'uploaded_by_user_id' => 1,
                'uploaded_by_name' => 'Alice',
            ]);
        }

        $result = $this->service->paginate(perPage: 3);

        $this->assertSame(5, $result->total());
        $this->assertSame(3, $result->perPage());
        $this->assertCount(3, $result->items());
        $this->assertInstanceOf(DocumentData::class, $result->items()[0]);
    }

    public function test_list_returns_collection_of_document_data(): void
    {
        Document::on('tenant')->create([
            'title' => 'First',
            'description' => null,
            'file_path' => 'documents/t/a.pdf',
            'file_name' => 'a.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => 1,
            'uploaded_by_name' => 'Alice',
        ]);

        $result = $this->service->list();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(DocumentData::class, $result->first());
        $this->assertSame('First', $result->first()->title);
    }

    public function test_find_returns_document_data(): void
    {
        $document = Document::on('tenant')->create([
            'title' => 'Find Me',
            'description' => 'Some desc',
            'file_path' => 'documents/t/find.pdf',
            'file_name' => 'find.pdf',
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => 1,
            'uploaded_by_name' => 'Bob',
        ]);

        $result = $this->service->find($document->id);

        $this->assertInstanceOf(DocumentData::class, $result);
        $this->assertSame('Find Me', $result->title);
        $this->assertSame('Some desc', $result->description);
    }

    public function test_store_creates_document_and_uploads_file(): void
    {
        $user = User::factory()->create(['name' => 'Carol']);
        $file = UploadedFile::fake()->create('report.pdf', 512, 'application/pdf');

        $result = $this->service->store(
            title: 'My Report',
            description: 'A description',
            file: $file,
            userId: $user->id,
            userName: $user->name,
            tenantSlug: 'acme',
            tenantId: 1,
        );

        $this->assertInstanceOf(DocumentData::class, $result);
        $this->assertSame('My Report', $result->title);
        $this->assertSame('report.pdf', $result->fileName);
        $this->assertDatabaseHas('documents', ['title' => 'My Report', 'uploaded_by_name' => 'Carol'], 'tenant');
        $this->fakeDisk->assertExists($this->service->getFilePath($result->id));
    }

    public function test_download_response_returns_a_response(): void
    {
        $this->fakeDisk->put('documents/acme/get.pdf', 'pdf-content');

        $document = Document::on('tenant')->create([
            'title' => 'Download Me',
            'description' => null,
            'file_path' => 'documents/acme/get.pdf',
            'file_name' => 'get.pdf',
            'file_size' => 11,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => 1,
            'uploaded_by_name' => 'Eve',
        ]);

        $response = $this->service->downloadResponse($document->id, 1);

        $this->assertThat(
            $response,
            $this->logicalOr(
                $this->isInstanceOf(StreamedResponse::class),
                $this->isInstanceOf(RedirectResponse::class),
            )
        );
    }

    public function test_delete_removes_document_and_file(): void
    {
        $this->fakeDisk->put('documents/acme/del.pdf', 'content');

        $document = Document::on('tenant')->create([
            'title' => 'Delete Me',
            'description' => null,
            'file_path' => 'documents/acme/del.pdf',
            'file_name' => 'del.pdf',
            'file_size' => 7,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => 1,
            'uploaded_by_name' => 'Dave',
        ]);

        $this->service->delete($document->id, 1);

        $this->assertDatabaseMissing('documents', ['id' => $document->id], 'tenant');
        $this->fakeDisk->assertMissing('documents/acme/del.pdf');
    }
}
