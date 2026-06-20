<?php

namespace Database\Seeders;

use App\Models\Central\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // The migration seeds the Demo Tenant on fresh installs.
        // This seeder handles environments where the migration seed was skipped.
        if (Tenant::where('slug', 'demo')->exists()) {
            return;
        }

        Tenant::factory()->create([
            'name' => 'Demo Tenant',
            'slug' => 'demo',
            'db_host' => env('DB_HOST', '127.0.0.1'),
            'db_port' => (int) env('DB_PORT', 5432),
            'db_name' => 'tenant_demo',
            'db_username' => env('DB_USERNAME', 'laravel'),
            'db_password' => env('DB_PASSWORD', 'secret'),
            'is_active' => true,
        ]);
    }
}
