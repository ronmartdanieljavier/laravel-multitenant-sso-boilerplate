<?php

namespace Database\Factories\Documents;

use App\Models\Tenant\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'file_path' => 'documents/1/'.fake()->uuid().'.pdf',
            'file_name' => fake()->word().'.pdf',
            'file_size' => fake()->numberBetween(1024, 10_485_760),
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => fake()->numberBetween(1, 100),
            'uploaded_by_name' => fake()->name(),
        ];
    }
}
