<?php

namespace App\Repositories\Central;

use App\Models\Central\TenantSetting;
use Illuminate\Support\Collection;

class TenantSettingRepository
{
    public function __construct(
        protected TenantSetting $model
    ) {}

    /**
     * Get all settings for a tenant as a key/value map.
     *
     * @return Collection<string, string|null>
     */
    public function allForTenant(int $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)->pluck('value', 'key');
    }

    public function get(int $tenantId, string $key, mixed $default = null): mixed
    {
        return $this->model->where('tenant_id', $tenantId)->where('key', $key)->value('value') ?? $default;
    }

    public function set(int $tenantId, string $key, mixed $value): void
    {
        $this->model->updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => $value]
        );
    }

    public function delete(int $tenantId, string $key): void
    {
        $this->model->where('tenant_id', $tenantId)->where('key', $key)->delete();
    }

    /**
     * Bulk upsert settings, deleting entries whose value is null.
     *
     * @param  array<string, mixed>  $data
     */
    public function setMany(int $tenantId, array $data): void
    {
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                $this->model->where('tenant_id', $tenantId)->where('key', $key)->delete();
            } else {
                $this->set($tenantId, $key, $value);
            }
        }
    }
}
