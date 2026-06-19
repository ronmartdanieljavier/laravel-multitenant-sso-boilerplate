<?php

namespace App\Login\Models;

use App\Login\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property int $app_id
 * @property Role $role
 * @property-read App $app
 * @property-read User $user
 */
#[Fillable(['user_id', 'app_id', 'role'])]
class UserApp extends Model
{
    protected function casts(): array
    {
        return [
            'role' => Role::class,
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
}
