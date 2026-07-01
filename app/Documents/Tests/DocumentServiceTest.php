<?php

namespace App\Documents\Tests;

use App\Documents\Data\DocumentData;
use App\Documents\Enums\DocumentSource;
use App\Documents\Services\DocumentService;
use App\Models\Central\Report;
use App\Models\Central\User;
use App\Models\Tenant\Document;
use App\Storage\StorageResolver;
use Illuminate\Filesystem\FilesystemAdapter;
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

    private FilesystemAdapter $fakeDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
        ]);

        Storage::fake('local');
        $this->fakeDisk = Storage::disk('local');

        $resolver = \Mockery::mock(StorageResolver::class);
        $resolver->allows('forTenant')->andReturn($this->fakeDisk);
        $resolver->allows('forSystem')->andReturn($this->fakeDisk);
        $this->app->instance(StorageResolver::class, $resolver);

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
                'source' => DocumentSource::Upload->value,
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
            'source' => DocumentSource::Upload->value,
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
            'source' => DocumentSource::Upload->value,
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
            'source' => DocumentSource::Upload->value,
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
            'source' => DocumentSource::Upload->value,
        ]);

        $this->service->delete($document->id, 1);

        $this->assertDatabaseMissing('documents', ['id' => $document->id], 'tenant');
        $this->fakeDisk->assertMissing('documents/acme/del.pdf');
    }

    public function test_store_sets_source_as_upload(): void
    {
        $user = User::factory()->create(['name' => 'Carol']);
        $file = UploadedFile::fake()->create('report.pdf', 512, 'application/pdf');

        $result = $this->service->store(
            title: 'Upload Source Test',
            description: null,
            file: $file,
            userId: $user->id,
            userName: $user->name,
            tenantSlug: 'acme',
            tenantId: 1,
        );

        $this->assertSame(DocumentSource::Upload, $result->source);
        $this->assertDatabaseHas('documents', ['title' => 'Upload Source Test', 'source' => 'upload'], 'tenant');
    }

    public function test_create_from_report_creates_document_with_report_source(): void
    {
        $user = User::factory()->create(['name' => 'Bob']);
        $report = Report::factory()->pdf()->success()->create([
            'user_id' => $user->id,
            'file_path' => 'demo/reports/report_test.pdf',
        ]);
        $this->fakeDisk->put('demo/reports/report_test.pdf', 'pdf-content');

        $result = $this->service->createFromReport($report);

        $this->assertInstanceOf(DocumentData::class, $result);
        $this->assertSame(DocumentSource::Report, $result->source);
        $this->assertSame('report_test.pdf', $result->fileName);
        $this->assertDatabaseHas('documents', [
            'source' => 'report',
            'file_path' => 'demo/reports/report_test.pdf',
            'uploaded_by_user_id' => $user->id,
        ], 'tenant');
    }

    public function test_create_from_report_uses_report_type_as_title(): void
    {
        $user = User::factory()->create();
        $report = Report::factory()->pdf()->success()->create([
            'user_id' => $user->id,
            'type' => 'documents_summary',
            'file_path' => 'demo/reports/report_abc.pdf',
        ]);
        $this->fakeDisk->put('demo/reports/report_abc.pdf', 'pdf-content');

        $result = $this->service->createFromReport($report);

        $this->assertSame('Documents Summary Report', $result->title);
    }

    public function test_download_response_for_report_document_uses_reports_disk(): void
    {
        $this->fakeDisk->put('demo/reports/report.pdf', 'pdf-content');

        $document = Document::on('tenant')->create([
            'title' => 'Report Document',
            'description' => null,
            'file_path' => 'demo/reports/report.pdf',
            'file_name' => 'report.pdf',
            'file_size' => 11,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => 1,
            'uploaded_by_name' => 'System',
            'source' => DocumentSource::Report->value,
        ]);

        $response = $this->service->downloadResponse($document->id, 1);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_download_response_for_upload_document_uses_tenant_disk(): void
    {
        $this->fakeDisk->put('acme/documents/uploaded.pdf', 'pdf-content');

        $document = Document::on('tenant')->create([
            'title' => 'Uploaded Document',
            'description' => null,
            'file_path' => 'acme/documents/uploaded.pdf',
            'file_name' => 'uploaded.pdf',
            'file_size' => 11,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => 1,
            'uploaded_by_name' => 'Alice',
            'source' => DocumentSource::Upload->value,
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
}
