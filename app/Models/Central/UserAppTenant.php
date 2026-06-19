<?php

namespace App\Models\Central;

use App\Auth\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property int $app_id
 * @property int $tenant_id
 * @property Role $role
 * @property bool $is_default
 * @property-read App $app
 * @property-read Tenant $tenant
 * @property-read User $user
 */
#[Fillable(['user_id', 'app_id', 'tenant_id', 'role', 'is_default'])]
class UserAppTenant extends Model
{
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
