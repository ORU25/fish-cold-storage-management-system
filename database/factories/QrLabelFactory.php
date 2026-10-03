<?php

namespace Database\Factories;

use App\Enums\QrLabelStatus;
use App\Models\QrLabel;
use App\Models\QrPrintBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrLabel>
 */
class QrLabelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'DUS-'.now()->format('ymd').'-'.fake()->unique()->numerify('9###'),
            'qr_print_batch_id' => QrPrintBatch::factory(),
            'status' => QrLabelStatus::Available,
        ];
    }
}
