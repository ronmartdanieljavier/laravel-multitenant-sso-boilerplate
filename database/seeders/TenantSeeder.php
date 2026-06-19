<?php

namespace Database\Seeders;

use App\Models\Central\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::factory()->create([
            'name' => 'Demo Tenant',
            'slug' => 'demo',
            'db_host' => env('DB_HOST', '127.0.0.1'),
            'db_port' => (int) env('DB_PORT', 5432),
            'db_name' => env('DB_DATABASE', 'laravel'),
            'db_username' => env('DB_USERNAME', 'laravel'),
            'db_password' => env('DB_PASSWORD', 'secret'),
            'is_active' => true,
        ]);
    }
}
