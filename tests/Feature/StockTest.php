<?php

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;

test('stock counts boxes in the warehouse and pending adjustment, in MC and KG', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['fish_name' => 'MB', 'grade' => 'A', 'size' => '3-5', 'kg_per_carton' => 10]);
    $blockA = Location::factory()->create(['name' => 'Blok A']);
    $blockB = Location::factory()->create(['name' => 'Blok B']);
    Box::factory()->count(2)->create(['product_id' => $product->id, 'location_id' => $blockA->id]);
    Box::factory()->create(['product_id' => $product->id, 'location_id' => $blockB->id, 'status' => BoxStatus::PendingAdjustment]);
    Box::factory()->create(['product_id' => $product->id, 'location_id' => $blockB->id, 'status' => BoxStatus::Outbound]);
    Box::factory()->create(['product_id' => $product->id, 'location_id' => $blockB->id, 'status' => BoxStatus::Lost]);
    Box::factory()->create(['product_id' => $product->id, 'location_id' => $blockA->id])->delete();

    $this->actingAs($admin)->get('/stock')
        ->assertInertia(fn ($page) => $page
            ->component('stock/index')
            ->where('perProduct', [['product' => 'MB A 3-5', 'mc' => 3, 'pending' => 1, 'available' => 2, 'kg' => 30]])
            ->where('perLocation', [
                ['location' => 'Blok A', 'product' => 'MB A 3-5', 'mc' => 2],
                ['location' => 'Blok B', 'product' => 'MB A 3-5', 'mc' => 1],
            ])
            ->where('boxes.total', 3));
});

test('box list can be filtered by status, location and code', function () {
    $owner = User::factory()->owner()->create();
    $blockA = Location::factory()->create();
    $inA = Box::factory()->create(['location_id' => $blockA->id]);
    Box::factory()->create();
    $outbound = Box::factory()->create(['status' => BoxStatus::Outbound]);

    $this->actingAs($owner)->get('/stock?location_id='.$blockA->id)
        ->assertInertia(fn ($page) => $page->has('boxes.data', 1)->where('boxes.data.0.id', $inA->id));

    $this->actingAs($owner)->get('/stock?status=outbound')
        ->assertInertia(fn ($page) => $page->has('boxes.data', 1)->where('boxes.data.0.id', $outbound->id));

    $this->actingAs($owner)->get('/stock?code='.strtolower($inA->qr_code))
        ->assertInertia(fn ($page) => $page->has('boxes.data', 1)->where('boxes.data.0.id', $inA->id));
});

test('staff can not see the stock page', function () {
    $this->actingAs(User::factory()->create())->get('/stock')->assertForbidden();
});
