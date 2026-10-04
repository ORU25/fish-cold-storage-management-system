<?php

use App\Enums\BoxStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\OutboundOrder;
use App\Models\OutboundScan;
use App\Models\Product;
use App\Models\User;

/**
 * An order with one item of two boxes, one of them already scanned out by the staff.
 *
 * @return array{0: OutboundOrder, 1: OutboundScan, 2: Box}
 */
function orderWithOneScan(OrderStatus $status = OrderStatus::Open): array
{
    $product = Product::factory()->create();
    $order = OutboundOrder::factory()->create(['status' => $status]);
    $item = $order->items()->create(['product_id' => $product->id, 'quantity_requested' => 2, 'quantity_scanned' => 1]);
    $staff = User::factory()->create();
    $box = Box::factory()->create(['product_id' => $product->id, 'status' => BoxStatus::Outbound, 'outbound_order_id' => $order->id, 'scanned_out_by' => $staff->id, 'scanned_out_at' => now()]);
    $scan = OutboundScan::create(['outbound_order_item_id' => $item->id, 'box_id' => $box->id, 'scanned_by' => $staff->id]);

    return [$order, $scan, $box];
}

test('admin cancels a wrong outbound scan: box returns to the warehouse, item needs it again, scan stays as history', function () {
    $admin = User::factory()->admin()->create();
    [$order, $scan, $box] = orderWithOneScan();

    $this->actingAs($admin)->post(route('outbound-scans.cancel', $scan), [])->assertSessionHasErrors('reason');
    $this->actingAs($admin)->post(route('outbound-scans.cancel', $scan), ['reason' => 'Salah scan dus'])->assertSessionHasNoErrors();

    expect($box->fresh())->status->toBe(BoxStatus::InWarehouse)->outbound_order_id->toBeNull()->scanned_out_by->toBeNull()
        ->and($order->items()->sole()->quantity_scanned)->toBe(0)
        ->and($scan->fresh())->cancelled_by->toBe($admin->id)->cancel_reason->toBe('Salah scan dus')->cancelled_at->not->toBeNull();

    expect(ActivityLog::firstWhere('action', 'box.outbound_cancelled'))
        ->box_id->toBe($box->id)
        ->user_id->toBe($admin->id)
        ->reason->toBe('Salah scan dus');

    $this->actingAs($admin)->post(route('outbound-scans.cancel', $scan), ['reason' => 'lagi'])->assertSessionHasErrors('reason');
});

test('the returned box can be scanned out again', function () {
    [$order, $scan, $box] = orderWithOneScan();
    $staff = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())->post(route('outbound-scans.cancel', $scan), ['reason' => 'Salah scan']);
    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $box->qr_code])->assertSessionHasNoErrors();

    expect($box->fresh()->status)->toBe(BoxStatus::Outbound)
        ->and($order->items()->sole()->quantity_scanned)->toBe(1);
});

test('scans of an order that is no longer open can not be cancelled', function (OrderStatus $status) {
    [$order, $scan, $box] = orderWithOneScan($status);

    $this->actingAs(User::factory()->admin()->create())->post(route('outbound-scans.cancel', $scan), ['reason' => 'x'])->assertSessionHasErrors('reason');

    expect($box->fresh()->status)->toBe(BoxStatus::Outbound)
        ->and($scan->fresh()->cancelled_at)->toBeNull();
})->with([OrderStatus::Completed, OrderStatus::Cancelled]);

test('staff can not cancel an outbound scan', function () {
    [$order, $scan, $box] = orderWithOneScan();

    $this->actingAs(User::factory()->create(['role' => Role::Staff]))->post(route('outbound-scans.cancel', $scan), ['reason' => 'x'])->assertForbidden();

    expect($box->fresh()->status)->toBe(BoxStatus::Outbound);
});
