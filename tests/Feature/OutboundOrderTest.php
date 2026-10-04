<?php

use App\Enums\BoxStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\OutboundOrder;
use App\Models\Product;
use App\Models\User;

/**
 * @param  array<string, int>  $quantities  product id => boxes
 */
function orderPayload(array $quantities, array $overrides = []): array
{
    return [
        'destination' => 'Toko Makmur',
        'order_date' => '2026-10-04',
        'notes' => null,
        'items' => array_map(fn (string $productId, int $quantity): array => ['product_id' => $productId, 'quantity' => $quantity], array_keys($quantities), $quantities),
        ...$overrides,
    ];
}

function orderWithItem(Product $product, int $requested, OrderStatus $status = OrderStatus::Open, int $scanned = 0): OutboundOrder
{
    $order = OutboundOrder::factory()->create(['status' => $status]);
    $order->items()->create(['product_id' => $product->id, 'quantity_requested' => $requested, 'quantity_scanned' => $scanned]);

    return $order;
}

test('admin creates a draft order with a daily order number', function () {
    $this->travelTo('2026-10-04 08:00');
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();
    Box::factory()->count(5)->create(['product_id' => $product->id]);

    $this->actingAs($admin)->post('/orders', orderPayload([$product->id => 3]))->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/orders', orderPayload([$product->id => 2]))->assertSessionHasNoErrors();

    $orders = OutboundOrder::orderBy('order_number')->get();
    expect($orders->pluck('order_number')->all())->toBe(['OUT-261004-001', 'OUT-261004-002'])
        ->and($orders->first())->status->toBe(OrderStatus::Draft)->created_by->toBe($admin->id)
        ->and($orders->first()->items()->sole())->product_id->toBe($product->id)->quantity_requested->toBe(3)
        ->and(ActivityLog::where('action', 'order.created')->count())->toBe(2);
});

test('admin can save and open an order in one step', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();
    Box::factory()->count(2)->create(['product_id' => $product->id]);

    $this->actingAs($admin)->post('/orders', orderPayload([$product->id => 2], ['open' => true]))->assertSessionHasNoErrors();

    expect(OutboundOrder::sole()->status)->toBe(OrderStatus::Open)
        ->and(ActivityLog::where('action', 'order.opened')->exists())->toBeTrue();
});

test('available stock is boxes in the warehouse minus what other open orders still need', function () {
    $product = Product::factory()->create();
    Box::factory()->count(5)->create(['product_id' => $product->id]);
    Box::factory()->create(['product_id' => $product->id, 'status' => BoxStatus::PendingAdjustment]);
    Box::factory()->create(['product_id' => $product->id, 'status' => BoxStatus::Outbound]);
    $open = orderWithItem($product, 3, scanned: 1);
    orderWithItem($product, 10, OrderStatus::Draft);
    orderWithItem($product, 10, OrderStatus::Cancelled);

    expect(OutboundOrder::availableStock([$product->id]))->toBe([$product->id => 3])
        ->and(OutboundOrder::availableStock([$product->id], except: $open))->toBe([$product->id => 5]);
});

test('an item above available stock is rejected', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['fish_name' => 'MB', 'grade' => 'A', 'size' => '3-5']);
    Box::factory()->count(5)->create(['product_id' => $product->id]);
    orderWithItem($product, 2);

    $this->actingAs($admin)->post('/orders', orderPayload([$product->id => 4]))
        ->assertSessionHasErrors(['items.0.quantity' => 'MB A 3-5: melebihi stok tersedia (3 dus).']);
    $this->actingAs($admin)->post('/orders', orderPayload([$product->id => 3]))->assertSessionHasNoErrors();

    expect(OutboundOrder::count())->toBe(2);
});

test('opening a draft re-checks stock taken by orders opened in the meantime', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();
    Box::factory()->count(3)->create(['product_id' => $product->id]);
    $draft = orderWithItem($product, 3, OrderStatus::Draft);
    orderWithItem($product, 1);

    $this->actingAs($admin)->post(route('orders.open', $draft))->assertSessionHasErrors('order');

    expect($draft->fresh()->status)->toBe(OrderStatus::Draft);
});

test('only drafts can be edited', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();
    Box::factory()->count(5)->create(['product_id' => $product->id]);
    $draft = orderWithItem($product, 1, OrderStatus::Draft);
    $open = orderWithItem($product, 1);

    $this->actingAs($admin)->put(route('orders.update', $draft), orderPayload([$product->id => 2], ['destination' => 'Toko Baru']))->assertSessionHasNoErrors();
    expect($draft->fresh()->destination)->toBe('Toko Baru')
        ->and($draft->items()->sole()->quantity_requested)->toBe(2);

    $this->actingAs($admin)->put(route('orders.update', $open), orderPayload([$product->id => 2]))->assertSessionHasErrors('order');
    expect($open->fresh()->status)->toBe(OrderStatus::Open);
});

test('a draft can be cancelled without a reason', function () {
    $draft = orderWithItem(Product::factory()->create(), 1, OrderStatus::Draft);

    $this->actingAs(User::factory()->admin()->create())->post(route('orders.cancel', $draft))->assertSessionHasNoErrors();

    expect($draft->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(ActivityLog::where('action', 'order.cancelled')->exists())->toBeTrue();
});

test('cancelling an open order needs a reason and returns its scanned boxes to the warehouse', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();
    Box::factory()->count(3)->create(['product_id' => $product->id]);
    $order = orderWithItem($product, 4, scanned: 2);
    $scanned = Box::factory()->count(2)->create(['product_id' => $product->id, 'status' => BoxStatus::Outbound, 'outbound_order_id' => $order->id]);

    $this->actingAs($admin)->post(route('orders.cancel', $order), [])->assertSessionHasErrors('cancel_reason');
    expect($order->fresh()->status)->toBe(OrderStatus::Open)
        ->and(OutboundOrder::availableStock([$product->id]))->toBe([$product->id => 1]);

    $this->actingAs($admin)->post(route('orders.cancel', $order), ['cancel_reason' => 'Pembeli hanya sanggup 2 dus'])->assertSessionHasNoErrors();

    expect($order->fresh())->status->toBe(OrderStatus::Cancelled)->cancel_reason->toBe('Pembeli hanya sanggup 2 dus')
        ->and($scanned->map->fresh()->pluck('status')->unique()->all())->toBe([BoxStatus::InWarehouse])
        ->and($scanned->first()->fresh()->outbound_order_id)->toBeNull()
        ->and($order->items()->sole()->quantity_scanned)->toBe(0)
        ->and(OutboundOrder::availableStock([$product->id]))->toBe([$product->id => 5])
        ->and(ActivityLog::where('action', 'box.outbound_cancelled')->count())->toBe(2)
        ->and(ActivityLog::firstWhere('action', 'order.cancelled')->reason)->toBe('Pembeli hanya sanggup 2 dus');
});

test('a completed order can not be cancelled', function () {
    $order = orderWithItem(Product::factory()->create(), 1, OrderStatus::Completed, scanned: 1);

    $this->actingAs(User::factory()->admin()->create())->post(route('orders.cancel', $order), ['cancel_reason' => 'x'])->assertSessionHasErrors('order');

    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('admin completes an order only after every item is scanned', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();
    $partial = orderWithItem($product, 2, scanned: 1);
    $full = orderWithItem($product, 2, scanned: 2);

    $this->actingAs($admin)->post(route('orders.complete', $partial))->assertSessionHasErrors('order');
    $this->actingAs($admin)->post(route('orders.complete', $full))->assertSessionHasNoErrors();

    expect($partial->fresh()->status)->toBe(OrderStatus::Open)
        ->and($full->fresh()->status)->toBe(OrderStatus::Completed)
        ->and(ActivityLog::firstWhere('action', 'order.completed')->user_id)->toBe($admin->id);
});

test('only open orders can be completed', function (OrderStatus $status) {
    $order = orderWithItem(Product::factory()->create(), 1, $status, scanned: 1);

    $this->actingAs(User::factory()->admin()->create())->post(route('orders.complete', $order))->assertSessionHasErrors('order');

    expect($order->fresh()->status)->toBe($status);
})->with([OrderStatus::Draft, OrderStatus::Cancelled]);

test('staff can not manage orders', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);
    $order = OutboundOrder::factory()->create();

    $this->actingAs($user)->get('/orders')->assertForbidden();
    $this->actingAs($user)->post('/orders', [])->assertForbidden();
    $this->actingAs($user)->post(route('orders.open', $order))->assertForbidden();
    $this->actingAs($user)->post(route('orders.complete', $order))->assertForbidden();
    $this->actingAs($user)->post(route('orders.cancel', $order), ['cancel_reason' => 'x'])->assertForbidden();
})->with([Role::Staff]);
