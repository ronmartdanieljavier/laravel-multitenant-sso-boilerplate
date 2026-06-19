<?php

namespace App\Login\Actions;

use App\Login\Data\Core\AppAccessCoreData;
use App\Login\Data\Core\AuthTokenCoreData;
use App\Login\Data\Core\LoginCredentialsCoreData;
use App\Login\Data\Core\UserCoreData;
use App\Login\Models\User;
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
