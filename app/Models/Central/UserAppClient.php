<?php

namespace App\Models\Central;

use App\Auth\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property int $app_id
 * @property int $client_id
 * @property Role $role
 * @property bool $is_default
 * @property-read App $app
 * @property-read Client $client
 * @property-read User $user
 */
#[Fillable(['user_id', 'app_id', 'client_id', 'role', 'is_default'])]
class UserAppClient extends Model
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
