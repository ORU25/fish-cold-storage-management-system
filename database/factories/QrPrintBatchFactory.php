<?php

namespace Database\Factories;

use App\Models\QrPrintBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrPrintBatch>
 */
class QrPrintBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quantity' => 1,
            'generated_by' => User::factory()->admin(),
        ];
    }
}
