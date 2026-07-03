<?php

namespace Tests;

use App\Auth\Enums\Role;
use App\Models\Central\App;
use App\Models\Central\User;
use App\Models\Central\UserApp;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Grant a user the admin role on the "admin" app, satisfying the
     * EnsureIsAdmin middleware that guards /admin routes.
     */
    protected function grantAdminRole(User $user): User
    {
        $app = App::query()->firstOrCreate(
            ['slug' => 'admin'],
            App::factory()->make(['slug' => 'admin'])->toArray(),
        );

        UserApp::query()->updateOrCreate(
            ['user_id' => $user->id, 'app_id' => $app->id],
            ['role' => Role::Admin],
        );

        return $user;
    }
}
