<?php

use App\Enums\BoxStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;

test('box detail shows the box and its history, with product and location names for the ids in the log', function () {
    $admin = User::factory()->admin()->create();
    $blockA = Location::factory()->create(['name' => 'Blok A']);
    $blockB = Location::factory()->create(['name' => 'Blok B']);
    $box = Box::factory()->create(['location_id' => $blockA->id]);

    $this->actingAs($admin)->post(route('boxes.move', $box), ['location_id' => $blockB->id])->assertSessionHasNoErrors();

    $this->actingAs(User::factory()->owner()->create())->get(route('boxes.show', $box))
        ->assertInertia(fn ($page) => $page
            ->component('boxes/show')
            ->where('box.qr_code', $box->qr_code)
            ->where('box.location.name', 'Blok B')
            ->where('history.0.action', 'box.location_changed')
            ->where('history.0.user.name', $admin->name)
            ->where("names.{$blockA->id}", 'Blok A')
            ->where("names.{$blockB->id}", 'Blok B'));

    $this->actingAs(User::factory()->create())->get(route('boxes.show', $box))->assertForbidden();
});

test('admin revises product and expiry with a reason, logging old and new values', function () {
    $admin = User::factory()->admin()->create();
    $box = Box::factory()->create(['expired_date' => '2027-01-01']);
    $other = Product::factory()->create();

    $this->actingAs($admin)->put(route('boxes.update', $box), ['product_id' => $other->id, 'expired_date' => '2027-03-01'])
        ->assertSessionHasErrors('reason');

    $this->actingAs($admin)->put(route('boxes.update', $box), ['product_id' => $other->id, 'expired_date' => '2027-03-01', 'reason' => 'Salah pilih produk'])
        ->assertSessionHasNoErrors();

    expect($box->fresh())->product_id->toBe($other->id)->and($box->fresh()->expired_date->toDateString())->toBe('2027-03-01');
    $log = ActivityLog::firstWhere('action', 'box.updated');
    expect($log)->box_id->toBe($box->id)->user_id->toBe($admin->id)->reason->toBe('Salah pilih produk')
        ->and($log->old_values)->toMatchArray(['product_id' => $box->product_id, 'expired_date' => '2027-01-01'])
        ->and($log->new_values)->toMatchArray(['product_id' => $other->id, 'expired_date' => '2027-03-01']);
});

test('an empty expiry is worked out from the production date and shelf life', function () {
    $product = Product::factory()->create(['shelf_life_days' => 100]);
    $box = Box::factory()->create(['product_id' => $product->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('boxes.update', $box), ['product_id' => $product->id, 'production_date' => '2026-01-01', 'reason' => 'Tanggal produksi terlewat'])
        ->assertSessionHasNoErrors();

    expect($box->fresh()->expired_date->toDateString())->toBe('2026-04-11');
});

test('a revision without changes, or of a box no longer in the warehouse, is rejected', function () {
    $admin = User::factory()->admin()->create();
    $box = Box::factory()->create(['expired_date' => '2027-01-01']);
    $outbound = Box::factory()->create(['status' => BoxStatus::Outbound, 'expired_date' => '2027-01-01']);

    $this->actingAs($admin)->put(route('boxes.update', $box), ['product_id' => $box->product_id, 'expired_date' => '2027-01-01', 'reason' => 'x'])
        ->assertSessionHasErrors(['reason' => 'Tidak ada data yang berubah.']);
    $this->actingAs($admin)->put(route('boxes.update', $outbound), ['product_id' => $outbound->product_id, 'expired_date' => '2027-05-01', 'reason' => 'x'])
        ->assertSessionHasErrors('reason');

    expect($outbound->fresh()->expired_date->toDateString())->toBe('2027-01-01')
        ->and(ActivityLog::where('action', 'box.updated')->exists())->toBeFalse();
});

test('moving one box logs the location change; inactive locations are refused', function () {
    $admin = User::factory()->admin()->create();
    $box = Box::factory()->create();
    $target = Location::factory()->create();
    $inactive = Location::factory()->inactive()->create();

    $this->actingAs($admin)->post(route('boxes.move', $box), ['location_id' => $inactive->id])->assertSessionHasErrors('location_id');
    $this->actingAs($admin)->post(route('boxes.move', $box), ['location_id' => $target->id])->assertSessionHasNoErrors();

    expect($box->fresh()->location_id)->toBe($target->id)
        ->and(ActivityLog::firstWhere('action', 'box.location_changed')->new_values)->toBe(['location_id' => $target->id]);
});

test('bulk move by scanning codes moves boxes in the warehouse and refuses the rest', function () {
    $admin = User::factory()->admin()->create();
    $target = Location::factory()->create(['name' => 'Blok C']);
    $box = Box::factory()->create();
    $outbound = Box::factory()->create(['status' => BoxStatus::Outbound]);
    $alreadyThere = Box::factory()->create(['location_id' => $target->id]);

    $this->actingAs($admin)->post(route('box-moves.store'), ['code' => strtolower($box->qr_code), 'location_id' => $target->id])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('box-moves.store'), ['code' => 'DUS-000000-0000', 'location_id' => $target->id])->assertSessionHasErrors('code');
    $this->actingAs($admin)->post(route('box-moves.store'), ['code' => $outbound->qr_code, 'location_id' => $target->id])->assertSessionHasErrors('code');
    $this->actingAs($admin)->post(route('box-moves.store'), ['code' => $alreadyThere->qr_code, 'location_id' => $target->id])
        ->assertSessionHasErrors(['code' => "Dus {$alreadyThere->qr_code} sudah di Blok C."]);

    expect($box->fresh()->location_id)->toBe($target->id)
        ->and($outbound->fresh()->location_id)->not->toBe($target->id)
        ->and(ActivityLog::where('action', 'box.location_changed')->count())->toBe(1);
});

test('staff can not revise or move boxes', function () {
    $staff = User::factory()->create(['role' => Role::Staff]);
    $box = Box::factory()->create();
    $location = Location::factory()->create();

    $this->actingAs($staff)->put(route('boxes.update', $box), ['product_id' => $box->product_id, 'reason' => 'x'])->assertForbidden();
    $this->actingAs($staff)->post(route('boxes.move', $box), ['location_id' => $location->id])->assertForbidden();
    $this->actingAs($staff)->get(route('box-moves.index'))->assertForbidden();
    $this->actingAs($staff)->post(route('box-moves.store'), ['code' => $box->qr_code, 'location_id' => $location->id])->assertForbidden();
});
