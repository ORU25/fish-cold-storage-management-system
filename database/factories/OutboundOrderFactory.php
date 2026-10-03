<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\OutboundOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboundOrder>
 */
class OutboundOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'OUT-'.now()->format('ymd').'-'.fake()->unique()->numerify('9##'),
            'destination' => fake()->company(),
            'order_date' => now()->toDateString(),
            'status' => OrderStatus::Draft,
            'created_by' => User::factory()->admin(),
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => OrderStatus::Open]);
    }
}
