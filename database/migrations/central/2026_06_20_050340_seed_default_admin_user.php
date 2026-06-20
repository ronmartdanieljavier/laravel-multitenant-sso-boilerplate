<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /** @var array<int, array{name: string, slug: string, path: string, description: string}> */
    private array $defaultApps = [
        [
            'name' => 'Admin',
            'slug' => 'admin',
            'path' => '/admin',
            'description' => 'Central administration — manage users, apps, tenants, and system settings',
        ],
        [
            'name' => 'Tenant',
            'slug' => 'tenant',
            'path' => '/tenant',
            'description' => 'Tenant portal — access and operate within a tenant database',
        ],
    ];

    public function up(): void
    {
        DB::table('users')->insertOrIgnore([
            'name' => env('ADMIN_NAME', 'Admin'),
            'email' => env('ADMIN_EMAIL', 'admin@example.com'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminId = DB::table('users')
            ->where('email', env('ADMIN_EMAIL', 'admin@example.com'))
            ->value('id');

        $baseUrl = env('APP_URL', 'http://localhost');

        foreach ($this->defaultApps as $appData) {
            DB::table('apps')->insertOrIgnore([
                'name' => $appData['name'],
                'slug' => $appData['slug'],
                'url' => $baseUrl.$appData['path'],
                'description' => $appData['description'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $apps = DB::table('apps')->get()->keyBy('slug');
        $demoTenant = DB::table('tenants')->where('slug', 'demo')->first();

        // Admin app — no tenant DB access needed; manages tenants through central DB
        if ($apps->has('admin')) {
            DB::table('user_apps')->insertOrIgnore([
                'user_id' => $adminId,
                'app_id' => $apps['admin']->id,
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Tenant app — grant access and link to the demo tenant DB
        if ($apps->has('tenant')) {
            DB::table('user_apps')->insertOrIgnore([
                'user_id' => $adminId,
                'app_id' => $apps['tenant']->id,
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($demoTenant) {
                DB::table('user_app_tenants')->insertOrIgnore([
                    'user_id' => $adminId,
                    'app_id' => $apps['tenant']->id,
                    'tenant_id' => $demoTenant->id,
                    'role' => 'admin',
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $adminId = DB::table('users')
            ->where('email', env('ADMIN_EMAIL', 'admin@example.com'))
            ->value('id');

        if ($adminId) {
            DB::table('user_app_tenants')->where('user_id', $adminId)->delete();
            DB::table('user_apps')->where('user_id', $adminId)->delete();
            DB::table('users')->where('id', $adminId)->delete();
        }

        DB::table('apps')->whereIn('slug', ['admin', 'tenant'])->delete();
    }
};
