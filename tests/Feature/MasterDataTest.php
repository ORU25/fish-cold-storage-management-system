<?php

use App\Models\ActivityLog;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;

test('admin can create a product and the display name and code are generated', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/products')->assertOk();
    $this->actingAs($admin)->post('/products', [
        'fish_name' => ' mb ',
        'grade' => 'a',
        'size' => '3-5',
        'kg_per_carton' => 10,
        'shelf_life_days' => 365,
    ])->assertSessionHasNoErrors();

    expect(Product::sole())->toMatchArray([
        'code' => 'MB-A-3-5',
        'fish_name' => 'MB',
        'grade' => 'A',
        'size' => '3-5',
        'display_name' => 'MB A 3-5',
        'kg_per_carton' => '10.00',
        'shelf_life_days' => 365,
        'is_active' => true,
    ])->and(ActivityLog::where('action', 'product.created')->exists())->toBeTrue();
});

test('grade, size and shelf life are optional; empty grade and size are stored as empty strings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/products', [
        'fish_name' => 'SARDEN',
        'grade' => '',
        'size' => '',
        'kg_per_carton' => 10,
        'shelf_life_days' => '',
    ])->assertSessionHasNoErrors();

    expect(Product::sole())->toMatchArray(['grade' => '', 'size' => '', 'display_name' => 'SARDEN', 'code' => 'SARDEN', 'shelf_life_days' => null]);
});

test('the same fish, grade and size combination can not be added twice', function () {
    $admin = User::factory()->admin()->create();
    Product::factory()->create(['fish_name' => 'MB', 'grade' => 'A', 'size' => '3-5']);
    $payload = ['fish_name' => 'mb', 'grade' => 'A', 'size' => '3-5', 'kg_per_carton' => 10];

    $this->actingAs($admin)->post('/products', $payload)->assertSessionHasErrors('fish_name');
    $this->actingAs($admin)->post('/products', [...$payload, 'size' => '6-10'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/products', [...$payload, 'grade' => 'B', 'size' => '6-10'])->assertSessionHasNoErrors();

    expect(Product::count())->toBe(3);
});

test('product codes must be unique', function () {
    $admin = User::factory()->admin()->create();
    Product::factory()->create(['code' => 'MB-A']);

    $this->actingAs($admin)->post('/products', ['fish_name' => 'LAIN', 'code' => 'mb-a', 'kg_per_carton' => 10])
        ->assertSessionHasErrors('code');
});

test('admin can update and deactivate a product and the change is logged', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['fish_name' => 'MB', 'grade' => 'A', 'size' => '3-5', 'code' => 'MB-A-3-5']);

    $this->actingAs($admin)->put("/products/{$product->id}", [
        'code' => 'MB-A-3-5',
        'fish_name' => 'MB',
        'grade' => 'A',
        'size' => '3-5',
        'kg_per_carton' => 12.5,
        'is_active' => false,
    ])->assertSessionHasNoErrors();

    expect($product->fresh())->kg_per_carton->toBe('12.50')->is_active->toBeFalse();

    $log = ActivityLog::firstWhere('action', 'product.updated');
    expect($log->old_values)->toMatchArray(['kg_per_carton' => '10.00', 'is_active' => true])
        ->and($log->new_values)->toMatchArray(['kg_per_carton' => '12.50', 'is_active' => false])
        ->and($log->user_id)->toBe($admin->id);
});

test('admin can manage locations with unique names', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/locations')->assertOk();
    $this->actingAs($admin)->post('/locations', ['name' => 'Blok A', 'description' => 'Dekat pintu'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/locations', ['name' => 'Blok A'])->assertSessionHasErrors('name');

    $location = Location::sole();
    $this->actingAs($admin)->put("/locations/{$location->id}", ['name' => 'Blok B', 'description' => '', 'is_active' => false])->assertSessionHasNoErrors();

    expect($location->fresh())->name->toBe('Blok B')->description->toBeNull()->is_active->toBeFalse()
        ->and(ActivityLog::where('action', 'location.updated')->exists())->toBeTrue();
});

test('active scope hides deactivated masters', function () {
    $active = Product::factory()->create();
    Product::factory()->inactive()->create();

    expect(Product::active()->pluck('id')->all())->toBe([$active->id]);
});

test('owner can manage products but not locations', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get('/products')->assertOk();
    $this->actingAs($owner)->post('/products', ['fish_name' => 'MB', 'grade' => 'A', 'kg_per_carton' => 10])->assertSessionHasNoErrors();
    $product = Product::sole();
    $this->actingAs($owner)->put("/products/{$product->id}", ['fish_name' => 'MB', 'grade' => 'B', 'kg_per_carton' => 10])->assertSessionHasNoErrors();

    expect($product->fresh()->display_name)->toBe('MB B')
        ->and(ActivityLog::where('action', 'product.updated')->value('user_id'))->toBe($owner->id);

    $this->actingAs($owner)->get('/locations')->assertForbidden();
    $this->actingAs($owner)->post('/locations', ['name' => 'Blok A'])->assertForbidden();
});

test('staff can not manage master data', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff)->get('/products')->assertForbidden();
    $this->actingAs($staff)->post('/products', ['fish_name' => 'MB'])->assertForbidden();
    $this->actingAs($staff)->get('/locations')->assertForbidden();
    $this->actingAs($staff)->post('/locations', ['name' => 'Blok A'])->assertForbidden();
});
