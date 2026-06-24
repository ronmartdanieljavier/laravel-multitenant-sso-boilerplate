<?php

namespace App\Repositories\Central;

use App\Admin\Data\UpdateAppData;
use App\Data\Repositories\Central\AppRepositoryData;
use App\Models\Central\App;
use Illuminate\Support\Collection;

class AppRepository
{
    public function __construct(
        protected App $model
    ) {}

    /**
     * @return Collection<int, AppRepositoryData>
     */
    public function listOrdered(): Collection
    {
        return $this->model->orderBy('name')->get()->map(fn (App $app) => AppRepositoryData::from($app));
    }

    public function update(int $id, UpdateAppData $data): AppRepositoryData
    {
        $app = $this->model->findOrFail($id);
        $app->update($data->toArray());

        return AppRepositoryData::from($app);
    }
}
