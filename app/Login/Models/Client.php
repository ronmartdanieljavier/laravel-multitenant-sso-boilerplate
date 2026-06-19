<?php

namespace App\Login\Models;

use Database\Factories\Login\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'db_host',
    'db_port',
    'db_name',
    'db_username',
    'db_password',
    'report_db_host',
    'report_db_name',
    'report_url',
    'report_server',
    'is_active',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }

    protected function casts(): array
    {
        return [
            'db_password' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_app_clients')
            ->withPivot('app_id', 'role', 'is_default')
            ->withTimestamps();
    }

    public function userAppClients(): HasMany
    {
        return $this->hasMany(UserAppClient::class);
    }
}
