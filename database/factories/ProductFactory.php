<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fish_name' => fake()->unique()->lexify('IKAN-????'),
            'grade' => fake()->randomElement(['', 'A', 'B', 'PP']),
            'size' => fake()->randomElement(['', '3-5', '6-10', '15-20']),
            'kg_per_carton' => 10,
            'shelf_life_days' => fake()->optional()->numberBetween(90, 720),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
