<?php

namespace App\Models\Central;

use Database\Factories\Auth\TenantFactory;
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
    'read_replica_host',
    'read_replica_port',
    'read_replica_username',
    'read_replica_password',
    'report_db_host',
    'report_db_name',
    'report_url',
    'report_server',
    'is_active',
    'is_maintenance',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    protected function casts(): array
    {
        return [
            'db_password' => 'encrypted',
            'read_replica_password' => 'encrypted',
            'is_active' => 'boolean',
            'is_maintenance' => 'boolean',
        ];
    }

    public function hasReadReplica(): bool
    {
        return $this->read_replica_host !== null;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_app_tenants')
            ->withPivot('app_id', 'role', 'is_default')
            ->withTimestamps();
    }

    public function userAppTenants(): HasMany
    {
        return $this->hasMany(UserAppTenant::class);
    }

    /** @return HasMany<TenantMigrationVersion, $this> */
    public function migrationVersions(): HasMany
    {
        return $this->hasMany(TenantMigrationVersion::class);
    }
}
