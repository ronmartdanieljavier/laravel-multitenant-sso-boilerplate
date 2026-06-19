<?php

namespace App\Models\Central;

use Database\Factories\Auth\AppFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'url', 'description', 'is_active'])]
class App extends Model
{
    /** @use HasFactory<AppFactory> */
    use HasFactory;

    protected static function newFactory(): AppFactory
    {
        return AppFactory::new();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_apps')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function userApps(): HasMany
    {
        return $this->hasMany(UserApp::class);
    }

    public function userAppTenants(): HasMany
    {
        return $this->hasMany(UserAppTenant::class);
    }
}
