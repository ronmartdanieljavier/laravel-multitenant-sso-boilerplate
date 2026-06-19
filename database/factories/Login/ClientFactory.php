<?php

namespace Database\Factories\Login;

use App\Login\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'db_host' => fake()->ipv4(),
            'db_port' => 3306,
            'db_name' => Str::snake(Str::slug($name)).'_db',
            'db_username' => fake()->userName(),
            'db_password' => fake()->password(),
            'report_db_host' => null,
            'report_db_name' => null,
            'report_url' => null,
            'report_server' => 'shared',
            'is_active' => true,
        ];
    }
}
