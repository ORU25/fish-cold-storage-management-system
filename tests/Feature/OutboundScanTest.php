<?php

use App\Enums\BoxStatus;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\Location;
use App\Models\OutboundOrder;
use App\Models\OutboundScan;
use App\Models\Product;
use App\Models\User;

function openOrderFor(Product $product, int $quantity, OrderStatus $status = OrderStatus::Open): OutboundOrder
{
    $order = OutboundOrder::factory()->create(['status' => $status]);
    $order->items()->create(['product_id' => $product->id, 'quantity_requested' => $quantity]);

    return $order;
}

test('scanning a matching box sends it out against the order', function () {
    $staff = User::factory()->create();
    $product = Product::factory()->create();
    $order = openOrderFor($product, 2);
    $box = Box::factory()->create(['product_id' => $product->id]);

    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => strtolower($box->qr_code)])->assertSessionHasNoErrors();

    expect($box->fresh())
        ->status->toBe(BoxStatus::Outbound)
        ->outbound_order_id->toBe($order->id)
        ->scanned_out_by->toBe($staff->id)
        ->scanned_out_at->not->toBeNull()
        ->and($order->items()->sole()->quantity_scanned)->toBe(1)
        ->and(OutboundScan::sole())->box_id->toBe($box->id)->fefo_violation->toBeFalse()
        ->and(ActivityLog::firstWhere('action', 'box.scanned_out')->box_id)->toBe($box->id)
        ->and($order->fresh()->status)->toBe(OrderStatus::Open);
});

test('a fully scanned order stays open for a manual check and accepts no more boxes', function () {
    $staff = User::factory()->create();
    $product = Product::factory()->create();
    $order = openOrderFor($product, 2);
    $boxes = Box::factory()->count(2)->create(['product_id' => $product->id, 'expired_date' => '2027-01-01']);

    foreach ($boxes as $box) {
        $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $box->qr_code])->assertSessionHasNoErrors();
    }

    expect($order->fresh()->status)->toBe(OrderStatus::Open)
        ->and(ActivityLog::where('action', 'order.completed')->exists())->toBeFalse();

    $extra = Box::factory()->create(['product_id' => $product->id, 'expired_date' => '2027-01-01']);
    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $extra->qr_code])->assertSessionHasErrors('code');
});

test('boxes that are not in the warehouse are rejected', function (BoxStatus $status) {
    $staff = User::factory()->create();
    $product = Product::factory()->create();
    $order = openOrderFor($product, 1);
    $box = Box::factory()->create(['product_id' => $product->id, 'status' => $status]);

    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $box->qr_code])->assertSessionHasErrors('code');

    expect($box->fresh()->status)->toBe($status)
        ->and(OutboundScan::count())->toBe(0);
})->with([BoxStatus::Outbound, BoxStatus::PendingAdjustment, BoxStatus::Lost, BoxStatus::Damaged]);

test('unknown codes and boxes whose inbound scan was cancelled are rejected', function () {
    $staff = User::factory()->create();
    $product = Product::factory()->create();
    $order = openOrderFor($product, 1);
    $cancelled = Box::factory()->create(['product_id' => $product->id]);
    $cancelled->delete();

    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => 'DUS-000000-0000'])->assertSessionHasErrors('code');
    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $cancelled->qr_code])->assertSessionHasErrors('code');

    expect(OutboundScan::count())->toBe(0);
});

test('a box whose product is not in the order, or whose item is already fulfilled, is rejected', function () {
    $staff = User::factory()->create();
    $product = Product::factory()->create();
    $order = openOrderFor($product, 1);
    $other = Box::factory()->create();
    [$first, $second] = Box::factory()->count(2)->create(['product_id' => $product->id, 'expired_date' => '2027-01-01']);
    $order->items()->create(['product_id' => Product::factory()->create()->id, 'quantity_requested' => 1]);

    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $other->qr_code])->assertSessionHasErrors('code');
    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $first->qr_code])->assertSessionHasNoErrors();
    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $second->qr_code])->assertSessionHasErrors('code');

    expect($second->fresh()->status)->toBe(BoxStatus::InWarehouse);
});

test('orders that are not open accept no scans', function (OrderStatus $status) {
    $staff = User::factory()->create();
    $product = Product::factory()->create();
    $order = openOrderFor($product, 1, $status);
    $box = Box::factory()->create(['product_id' => $product->id]);

    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $box->qr_code])->assertSessionHasErrors('code');

    expect($box->fresh()->status)->toBe(BoxStatus::InWarehouse);
})->with([OrderStatus::Draft, OrderStatus::Completed, OrderStatus::Cancelled]);

test('a box with an earlier-expiring match still in stock needs a FEFO reason', function () {
    $staff = User::factory()->create();
    $product = Product::factory()->create(['fish_name' => 'MB', 'grade' => 'A', 'size' => '3-5']);
    $order = openOrderFor($product, 1);
    Box::factory()->count(2)->create(['product_id' => $product->id, 'expired_date' => '2027-01-01']);
    $later = Box::factory()->create(['product_id' => $product->id, 'expired_date' => '2027-03-01']);

    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $later->qr_code])
        ->assertSessionHasErrors(['fefo' => 'Masih ada 2 dus MB A 3-5 dengan expired lebih awal (01/01/2027). Isi alasan untuk tetap mengeluarkan dus ini.']);
    expect($later->fresh()->status)->toBe(BoxStatus::InWarehouse);

    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $later->qr_code, 'fefo_reason' => 'Tertumpuk di bawah'])->assertSessionHasNoErrors();

    expect($later->fresh()->status)->toBe(BoxStatus::Outbound)
        ->and(OutboundScan::sole())->fefo_violation->toBeTrue()->fefo_reason->toBe('Tertumpuk di bawah');
    $log = ActivityLog::firstWhere('action', 'box.fefo_override');
    expect($log)->box_id->toBe($later->id)->reason->toBe('Tertumpuk di bawah');
});

test('boxes with the same expiry date are not a FEFO violation', function () {
    $staff = User::factory()->create();
    $product = Product::factory()->create();
    $order = openOrderFor($product, 1);
    Box::factory()->create(['product_id' => $product->id, 'expired_date' => '2027-01-01']);
    $box = Box::factory()->create(['product_id' => $product->id, 'expired_date' => '2027-01-01']);

    $this->actingAs($staff)->post(route('outbound.scans.store', $order), ['code' => $box->qr_code])->assertSessionHasNoErrors();

    expect(OutboundScan::sole()->fefo_violation)->toBeFalse();
});

test('the pick list recommends the earliest-expiring boxes, grouped by date and location', function () {
    $staff = User::factory()->create();
    $product = Product::factory()->create(['fish_name' => 'MB', 'grade' => 'A', 'size' => '3-5']);
    $order = openOrderFor($product, 3);
    $blockA = Location::factory()->create(['name' => 'Blok A']);
    $blockB = Location::factory()->create(['name' => 'Blok B']);
    $soonA = Box::factory()->create(['product_id' => $product->id, 'location_id' => $blockA->id, 'expired_date' => '2027-01-01']);
    $soonB = Box::factory()->create(['product_id' => $product->id, 'location_id' => $blockB->id, 'expired_date' => '2027-01-01']);
    $next = Box::factory()->create(['product_id' => $product->id, 'location_id' => $blockA->id, 'expired_date' => '2027-02-01']);
    Box::factory()->create(['product_id' => $product->id, 'location_id' => $blockA->id, 'expired_date' => '2027-06-01']);

    $groups = [
        ['expired_date' => '2027-01-01', 'location' => 'Blok A', 'codes' => [$soonA->qr_code]],
        ['expired_date' => '2027-01-01', 'location' => 'Blok B', 'codes' => [$soonB->qr_code]],
        ['expired_date' => '2027-02-01', 'location' => 'Blok A', 'codes' => [$next->qr_code]],
    ];
    usort($groups, fn (array $a, array $b): int => [$a['expired_date'], $a['location'] === 'Blok A' ? $blockA->id : $blockB->id] <=> [$b['expired_date'], $b['location'] === 'Blok A' ? $blockA->id : $blockB->id]);

    $this->actingAs($staff)->get(route('outbound.show', $order))
        ->assertInertia(fn ($page) => $page
            ->component('outbound/show')
            ->where('pickList', [['product' => 'MB A 3-5', 'remaining' => 3, 'groups' => $groups]]));
});

test('only open orders are listed for staff, and owner can do outbound too', function () {
    $staff = User::factory()->create();
    $product = Product::factory()->create();
    $open = openOrderFor($product, 1);
    openOrderFor($product, 1, OrderStatus::Draft);

    $this->actingAs($staff)->get('/outbound')
        ->assertInertia(fn ($page) => $page->has('orders', 1)->where('orders.0.id', $open->id));

    $owner = User::factory()->owner()->create();
    $this->actingAs($owner)->get('/outbound')->assertOk();
    $this->actingAs($owner)->post(route('outbound.scans.store', $open), ['code' => 'x'])->assertSessionHasErrors('code');
});
