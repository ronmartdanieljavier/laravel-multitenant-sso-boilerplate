<?php

namespace App\Auth\Actions;

use App\Auth\Data\Core\AppAccessCoreData;
use App\Auth\Data\Core\AuthTokenCoreData;
use App\Auth\Data\Core\LoginCredentialsCoreData;
use App\Auth\Data\Core\UserCoreData;
use App\Models\Central\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    public function __construct(
        private readonly LoadUserAppsAction $loadUserAppsAction,
    ) {}

    /**
     * @throws AuthenticationException
     */
    public function execute(LoginCredentialsCoreData $credentials): AuthTokenCoreData
    {
        $user = User::where('email', $credentials->email)->first();

        if (! $user || ! Hash::check($credentials->password, $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $apps = $this->loadUserAppsAction->execute($user);

        $abilities = $apps->toCollection()
            ->map(fn (AppAccessCoreData $app): string => "app:{$app->slug}")
            ->all();

        $token = $user->createToken('sso', $abilities)->plainTextToken;

        return new AuthTokenCoreData(
            token: $token,
            tokenType: 'Bearer',
            user: new UserCoreData(
                id: $user->id,
                name: $user->name,
                email: $user->email,
            ),
            apps: $apps,
        );
    }
}
