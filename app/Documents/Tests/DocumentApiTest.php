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

class DocumentApiTest extends TestCase
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

    private function apiHeaders(): array
    {
        return [
            'X-App' => $this->tenantApp->slug,
            'X-Tenant' => $this->tenant->slug,
        ];
    }

    private function token(): string
    {
        return $this->user->createToken('sso', ["app:{$this->tenantApp->slug}"])->plainTextToken;
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/documents')->assertUnauthorized();
    }

    public function test_index_returns_documents_list(): void
    {
        Document::on('tenant')->create([
            'title' => 'API Doc',
            'description' => null,
            'file_path' => 'documents/test/api.pdf',
            'file_name' => 'api.pdf',
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => $this->user->id,
            'uploaded_by_name' => $this->user->name,
        ]);

        $this->withToken($this->token())
            ->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/documents')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'file_name', 'file_size', 'mime_type', 'uploaded_by_name', 'created_at']],
                'current_page',
                'total',
                'per_page',
                'last_page',
            ]);
    }

    public function test_store_creates_document(): void
    {
        $this->withToken($this->token())
            ->withHeaders($this->apiHeaders())
            ->post('/api/v1/documents', [
                'title' => 'New API Doc',
                'file' => UploadedFile::fake()->create('doc.pdf', 128, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'New API Doc');

        $this->assertDatabaseHas('documents', ['title' => 'New API Doc'], 'tenant');
    }

    public function test_store_validates_required_fields(): void
    {
        $this->withToken($this->token())
            ->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/documents', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'file']);
    }

    public function test_store_rejects_disallowed_mime_type(): void
    {
        $this->mockSettingsService(
            allowedTypes: ['pdf'],
            maxSizes: ['pdf' => 5],
        );

        $this->withToken($this->token())
            ->withHeaders($this->apiHeaders())
            ->post('/api/v1/documents', [
                'title' => 'Wrong Type',
                'file' => UploadedFile::fake()->create('data.csv', 50, 'text/csv'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_store_rejects_file_exceeding_per_type_max_size(): void
    {
        $this->mockSettingsService(
            allowedTypes: ['pdf'],
            maxSizes: ['pdf' => 1],
        );

        // 1.5 MB — over the 1 MB limit
        $this->withToken($this->token())
            ->withHeaders($this->apiHeaders())
            ->post('/api/v1/documents', [
                'title' => 'Huge PDF',
                'file' => UploadedFile::fake()->create('huge.pdf', 1536, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_store_accepts_allowed_file_type_within_size_limit(): void
    {
        $this->mockSettingsService(
            allowedTypes: ['image'],
            maxSizes: ['image' => 5],
        );

        $this->withToken($this->token())
            ->withHeaders($this->apiHeaders())
            ->post('/api/v1/documents', [
                'title' => 'Screenshot',
                'file' => UploadedFile::fake()->create('screen.png', 512, 'image/png'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Screenshot');
    }

    public function test_show_returns_document(): void
    {
        $document = Document::on('tenant')->create([
            'title' => 'Show Me',
            'description' => 'Details here',
            'file_path' => 'documents/test/show.pdf',
            'file_name' => 'show.pdf',
            'file_size' => 512,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => $this->user->id,
            'uploaded_by_name' => $this->user->name,
        ]);

        $this->withToken($this->token())
            ->withHeaders($this->apiHeaders())
            ->getJson("/api/v1/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('title', 'Show Me');
    }

    public function test_destroy_deletes_document(): void
    {
        Storage::fake('public');

        $document = Document::on('tenant')->create([
            'title' => 'Destroy Me',
            'description' => null,
            'file_path' => 'documents/test/bye.pdf',
            'file_name' => 'bye.pdf',
            'file_size' => 256,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => $this->user->id,
            'uploaded_by_name' => $this->user->name,
        ]);

        $this->withToken($this->token())
            ->withHeaders($this->apiHeaders())
            ->deleteJson("/api/v1/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Document deleted.');

        $this->assertDatabaseMissing('documents', ['id' => $document->id], 'tenant');
    }
}
