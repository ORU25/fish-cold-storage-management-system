<?php

use App\Enums\BoxStatus;
use App\Models\ActivityLog;
use App\Models\Adjustment;
use App\Models\Box;
use App\Models\OutboundOrder;
use App\Models\OutboundOrderItem;
use App\Models\OutboundScan;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('staff only get the quick links', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')
        ->assertInertia(fn ($page) => $page->component('dashboard')->where('stock', null)->where('owner', null));
});

test('admin sees the stock recap and boxes near expiry, but not the owner panels', function () {
    $soon = Box::factory()->create(['expired_date' => today()->addDays(10)]);
    Box::factory()->create(['expired_date' => today()->addDays(60)]);
    Box::factory()->create(['expired_date' => today()->addDays(5), 'status' => BoxStatus::Outbound]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('owner', null)
            ->has('stock.perProduct', 2)
            ->where('stock.days', 30)
            ->has('stock.nearExpiry', 1)
            ->where('stock.nearExpiry.0.id', $soon->id));

    $this->actingAs($admin)->get('/dashboard?days=90')->assertInertia(fn ($page) => $page->has('stock.nearExpiry', 2));
});

test('owner sees pending adjustments, FEFO violations, admin corrections and daily in/out', function () {
    $pending = Adjustment::factory()->create();
    $box = Box::factory()->create(['status' => BoxStatus::Outbound, 'scanned_out_at' => now()]);
    $item = OutboundOrderItem::create(['outbound_order_id' => OutboundOrder::factory()->open()->create()->id, 'product_id' => $box->product_id, 'quantity_requested' => 1, 'quantity_scanned' => 1]);
    OutboundScan::create(['outbound_order_item_id' => $item->id, 'box_id' => $box->id, 'scanned_by' => User::factory()->create()->id, 'fefo_violation' => true, 'fefo_reason' => 'Tertutup tumpukan']);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    ActivityLog::record('box.updated', $box, ['expired_date' => '2027-01-01'], ['expired_date' => '2027-02-01'], 'Salah input');
    ActivityLog::record('product.created', $box->product);

    $this->actingAs(User::factory()->owner()->create())->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('owner.pendingAdjustments.0.id', $pending->id)
            ->where('owner.fefoViolations.0.fefo_reason', 'Tertutup tumpukan')
            ->has('owner.adminActions', 1)
            ->where('owner.adminActions.0.subject', $box->qr_code)
            ->where('owner.adminActions.0.user', $admin->name)
            ->has('owner.daily', 14)
            ->where('owner.daily.13', ['date' => today()->toDateString(), 'in' => 2, 'out' => 1]));
});
