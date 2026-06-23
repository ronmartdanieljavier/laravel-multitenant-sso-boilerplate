<?php

namespace App\Auth\Services;

use App\Auth\Data\AppAccessData;
use App\Auth\Data\AuthTokenData;
use App\Auth\Data\LoginCredentialsData;
use App\Auth\Data\UserData;
use App\Repositories\Central\UserRepository;
use Illuminate\Auth\AuthenticationException;

class AuthService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected AppService $appService,
    ) {}

    /**
     * Authenticate a user and return a token with their app access.
     *
     * @throws AuthenticationException
     */
    public function login(LoginCredentialsData $credentials): AuthTokenData
    {
        $userData = $this->userRepository->findByEmail($credentials->email);

        if (! $userData || ! $userData->id || ! $this->userRepository->verifyPassword($userData->id, $credentials->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $apps = $this->appService->loadApps($userData->id);

        $abilities = $apps->toCollection()
            ->map(fn (AppAccessData $app): string => "app:{$app->slug}")
            ->all();

        $token = $this->userRepository->createSanctumToken($userData->id, 'sso', $abilities);

        return new AuthTokenData(
            token: $token,
            tokenType: 'Bearer',
            user: new UserData(
                id: $userData->id,
                name: $userData->name,
                email: $userData->email,
            ),
            apps: $apps,
        );
    }
}
