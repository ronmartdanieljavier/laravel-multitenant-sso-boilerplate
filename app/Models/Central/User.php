<?php

namespace App\Models\Central;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Storage\StorageResolver;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property Carbon|null $invitation_sent_at
 * @property string|null $profile_picture_url
 */
#[Fillable(['name', 'email', 'password', 'profile_picture', 'is_active', 'invitation_token', 'invitation_sent_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function profilePictureUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->profile_picture
                ? app(StorageResolver::class)->forSystem()->url($this->profile_picture)
                : null,
        );
    }

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
            'is_active' => 'boolean',
            'invitation_sent_at' => 'datetime',
        ];
    }
}
