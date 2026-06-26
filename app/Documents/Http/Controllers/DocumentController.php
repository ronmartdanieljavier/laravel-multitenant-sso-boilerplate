<?php

namespace App\Documents\Http\Controllers;

use App\Documents\Http\Requests\StoreDocumentRequest;
use App\Documents\Services\DocumentService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService
    ) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('current_tenant');

        return Inertia::render('Documents/Index', [
            'documents' => $this->documentService->paginate(),
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('current_tenant');

        $this->documentService->store(
            title: $request->string('title')->toString(),
            description: $request->input('description'),
            file: $request->file('file'),
            userId: $user->id,
            userName: $user->name,
            tenantSlug: $tenant->slug,
            tenantId: $tenant->id,
        );

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function download(Request $request, int $id): SymfonyResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('current_tenant');

        return $this->documentService->downloadResponse($id, $tenant->id);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('current_tenant');

        $this->documentService->delete($id, $tenant->id);

        return back()->with('success', 'Document deleted.');
    }
}
