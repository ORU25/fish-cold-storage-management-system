<?php

use App\Enums\BoxStatus;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\OutboundOrder;
use App\Models\OutboundScan;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;

test('demo seeder builds a consistent warehouse and releases the clock', function () {
    $this->seed(DemoSeeder::class);

    expect(User::count())->toBe(5)
        ->and(Box::where('status', BoxStatus::InWarehouse)->count())->toBe(48)
        ->and(Box::where('status', BoxStatus::Outbound)->count())->toBe(14)
        ->and(OutboundScan::count())->toBe(20)
        ->and(OutboundScan::whereNotNull('cancelled_at')->count())->toBe(6)
        ->and(ActivityLog::whereIn('action', ['box.updated', 'box.location_changed', 'box.inbound_cancelled', 'box.outbound_cancelled'])->pluck('action')->countBy()->all())
        ->toEqual(['box.updated' => 2, 'box.location_changed' => 4, 'box.inbound_cancelled' => 1, 'box.outbound_cancelled' => 6])
        ->and(OutboundOrder::pluck('status')->countBy(fn (OrderStatus $status) => $status->value)->all())
        ->toEqual(['completed' => 2, 'cancelled' => 2, 'open' => 1, 'draft' => 1])
        ->and(OutboundScan::where('fefo_violation', true)->count())->toBe(1)
        ->and(Carbon::hasTestNow())->toBeFalse();

    $this->actingAs(User::where('username', 'admin')->sole())->get('/stock')->assertOk();
});
