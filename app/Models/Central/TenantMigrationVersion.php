<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'migration', 'batch', 'migrated_at'])]
class TenantMigrationVersion extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'migrated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
