<?php

namespace Database\Factories\Login;

use App\Login\Models\App;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<App>
 */
class AppFactory extends Factory
{
    protected $model = App::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'url' => fake()->url(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
