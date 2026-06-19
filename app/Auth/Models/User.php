<?php

namespace App\Auth\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function apps(): BelongsToMany
    {
        return $this->belongsToMany(App::class, 'user_apps')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<UserApp, $this> */
    public function userApps(): HasMany
    {
        return $this->hasMany(UserApp::class);
    }

    /** @return HasMany<UserAppTenant, $this> */
    public function userAppTenants(): HasMany
    {
        return $this->hasMany(UserAppTenant::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
