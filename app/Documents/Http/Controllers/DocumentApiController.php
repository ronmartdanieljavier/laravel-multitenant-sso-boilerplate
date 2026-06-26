<?php

namespace App\Documents\Http\Controllers;

use App\Documents\Data\DocumentData;
use App\Documents\Http\Requests\StoreDocumentRequest;
use App\Documents\Services\DocumentService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DocumentApiController extends Controller
{
    public function __construct(
        protected DocumentService $documentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        return response()->json($this->documentService->paginate($perPage));
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('current_tenant');

        $document = $this->documentService->store(
            title: $request->string('title')->toString(),
            description: $request->input('description'),
            file: $request->file('file'),
            userId: $user->id,
            userName: $user->name,
            tenantSlug: $tenant->slug,
            tenantId: $tenant->id,
        );

        return response()->json(['data' => $document], Response::HTTP_CREATED);
    }

    public function show(int $id): DocumentData
    {
        return $this->documentService->find($id);
    }

    public function download(Request $request, int $id): SymfonyResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('current_tenant');

        return $this->documentService->downloadResponse($id, $tenant->id);
    }

    public function downloadZip(Request $request): SymfonyResponse
    {
        $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']]);

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('current_tenant');

        return $this->documentService->downloadZipResponse(
            array_map('intval', $request->input('ids')),
            $tenant->id,
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('current_tenant');

        $this->documentService->delete($id, $tenant->id);

        return response()->json(['message' => 'Document deleted.']);
    }
}
