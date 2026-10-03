<?php

namespace Database\Factories;

use App\Enums\BoxStatus;
use App\Enums\QrLabelStatus;
use App\Models\Box;
use App\Models\InboundBatch;
use App\Models\Location;
use App\Models\Product;
use App\Models\QrLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Box>
 */
class BoxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qr_label_id' => QrLabel::factory()->state(['status' => QrLabelStatus::Used, 'used_at' => now()]),
            'qr_code' => fn (array $attributes): string => QrLabel::find($attributes['qr_label_id'])->code,
            'inbound_batch_id' => InboundBatch::factory(),
            'product_id' => Product::factory(),
            'location_id' => Location::factory(),
            'expired_date' => now()->addMonths(6)->toDateString(),
            'status' => BoxStatus::InWarehouse,
            'scanned_in_by' => User::factory(),
            'scanned_in_at' => now(),
        ];
    }
}
