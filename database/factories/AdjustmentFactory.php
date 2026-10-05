<?php

namespace Database\Factories;

use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\BoxStatus;
use App\Models\Adjustment;
use App\Models\Box;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Adjustment>
 */
class AdjustmentFactory extends Factory
{
    /**
     * A pending request, so its box waits for the decision.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'box_id' => Box::factory()->state(['status' => BoxStatus::PendingAdjustment]),
            'type' => AdjustmentType::Lost,
            'reason' => fake()->sentence(),
            'status' => AdjustmentStatus::Pending,
            'requested_by' => User::factory()->admin(),
        ];
    }
}
