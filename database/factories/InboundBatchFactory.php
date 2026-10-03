<?php

namespace Database\Factories;

use App\Models\InboundBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboundBatch>
 */
class InboundBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_name' => fake()->company(),
            'delivery_note_number' => fake()->optional()->numerify('SJ-####'),
            'created_by' => User::factory(),
            'started_at' => now(),
        ];
    }
}
