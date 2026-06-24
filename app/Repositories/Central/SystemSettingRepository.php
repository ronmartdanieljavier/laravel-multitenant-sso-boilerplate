<?php

namespace App\Repositories\Central;

use App\Models\Central\SystemSetting;

class SystemSettingRepository
{
    public function __construct(
        protected SystemSetting $model
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->model->where('key', $key)->value('value') ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->model->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
