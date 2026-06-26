<?php

namespace App\Repositories\Central;

use App\Data\Repositories\Central\DocumentRepositoryData;
use App\Models\Tenant\Document;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DocumentRepository
{
    public function __construct(
        protected Document $model
    ) {}

    /**
     * @return LengthAwarePaginator<DocumentRepositoryData>
     */
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->on('tenant')
            ->latest()
            ->paginate($perPage)
            ->through(fn (Document $doc) => DocumentRepositoryData::from($doc));
    }

    /**
     * @return Collection<int, DocumentRepositoryData>
     */
    public function list(): Collection
    {
        return $this->model->on('tenant')
            ->latest()
            ->get()
            ->map(fn (Document $doc) => DocumentRepositoryData::from($doc));
    }

    public function find(int $id): DocumentRepositoryData
    {
        return DocumentRepositoryData::from(
            $this->model->on('tenant')->findOrFail($id)
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): DocumentRepositoryData
    {
        return DocumentRepositoryData::from(
            $this->model->on('tenant')->create($attributes)
        );
    }

    public function delete(int $id): void
    {
        $this->model->on('tenant')->findOrFail($id)->delete();
    }
}
