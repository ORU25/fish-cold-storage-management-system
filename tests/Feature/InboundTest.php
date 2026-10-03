<?php

use App\Enums\BoxStatus;
use App\Enums\QrLabelStatus;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\InboundBatch;
use App\Models\Location;
use App\Models\Product;
use App\Models\QrLabel;
use App\Models\User;

/**
 * @return array<string, mixed>
 */
function scanPayload(string $code, array $overrides = []): array
{
    return [
        'code' => $code,
        'product_id' => Product::factory()->create(['shelf_life_days' => null])->id,
        'location_id' => Location::factory()->create()->id,
        'production_date' => null,
        'expired_date' => '2027-04-01',
        ...$overrides,
    ];
}

test('staff can start a batch', function () {
    $staff = User::factory()->create();

    $response = $this->actingAs($staff)->post('/inbound', ['supplier_name' => 'PT Laut', 'delivery_note_number' => 'SJ-1']);

    $batch = InboundBatch::sole();
    $response->assertRedirect(route('inbound.show', $batch));
    expect($batch->created_by)->toBe($staff->id)
        ->and($batch->finished_at)->toBeNull();
});

test('scanning an available sticker creates a box with the batch values and marks the sticker used', function () {
    $this->travelTo('2026-10-03 10:00');
    $staff = User::factory()->create();
    $batch = InboundBatch::factory()->create();
    $label = QrLabel::factory()->create();
    $payload = scanPayload(strtolower($label->code));

    $this->actingAs($staff)->post(route('inbound.scans.store', $batch), $payload)->assertSessionHasNoErrors();

    $box = Box::sole();
    expect($box)
        ->qr_code->toBe($label->code)
        ->inbound_batch_id->toBe($batch->id)
        ->product_id->toBe($payload['product_id'])
        ->location_id->toBe($payload['location_id'])
        ->status->toBe(BoxStatus::InWarehouse)
        ->scanned_in_by->toBe($staff->id)
        ->and($box->expired_date->toDateString())->toBe('2027-04-01')
        ->and($label->fresh())->status->toBe(QrLabelStatus::Used)->used_at->not->toBeNull();

    $log = ActivityLog::firstWhere('action', 'box.scanned_in');
    expect($log->box_id)->toBe($box->id)
        ->and($log->user_id)->toBe($staff->id);
});

test('expired date is calculated from production date and product shelf life', function () {
    $staff = User::factory()->create();
    $batch = InboundBatch::factory()->create();
    $label = QrLabel::factory()->create();
    $product = Product::factory()->create(['shelf_life_days' => 365]);

    $this->actingAs($staff)->post(route('inbound.scans.store', $batch), scanPayload($label->code, [
        'product_id' => $product->id,
        'production_date' => '2026-09-01',
        'expired_date' => null,
    ]))->assertSessionHasNoErrors();

    expect(Box::sole()->expired_date->toDateString())->toBe('2027-09-01');
});

test('a manually entered expired date wins over the calculated one', function () {
    $staff = User::factory()->create();
    $label = QrLabel::factory()->create();

    $this->actingAs($staff)->post(route('inbound.scans.store', InboundBatch::factory()->create()), scanPayload($label->code, [
        'product_id' => Product::factory()->create(['shelf_life_days' => 365])->id,
        'production_date' => '2026-09-01',
        'expired_date' => '2027-01-15',
    ]))->assertSessionHasNoErrors();

    expect(Box::sole()->expired_date->toDateString())->toBe('2027-01-15');
});

test('scan is rejected without an expired date when it can not be calculated', function () {
    $staff = User::factory()->create();
    $label = QrLabel::factory()->create();

    $this->actingAs($staff)->post(route('inbound.scans.store', InboundBatch::factory()->create()), scanPayload($label->code, [
        'production_date' => '2026-09-01',
        'expired_date' => null,
    ]))->assertSessionHasErrors('expired_date');

    expect(Box::count())->toBe(0)
        ->and($label->fresh()->status)->toBe(QrLabelStatus::Available);
});

test('unknown, used and void stickers are rejected', function (?QrLabelStatus $status) {
    $staff = User::factory()->create();
    $code = $status ? QrLabel::factory()->create(['status' => $status])->code : 'DUS-999999-9999';

    $this->actingAs($staff)->post(route('inbound.scans.store', InboundBatch::factory()->create()), scanPayload($code))
        ->assertSessionHasErrors('code');

    expect(Box::count())->toBe(0);
})->with([null, QrLabelStatus::Used, QrLabelStatus::Void]);

test('the same sticker can only be scanned once', function () {
    $staff = User::factory()->create();
    $batch = InboundBatch::factory()->create();
    $label = QrLabel::factory()->create();

    $this->actingAs($staff)->post(route('inbound.scans.store', $batch), scanPayload($label->code))->assertSessionHasNoErrors();
    $this->actingAs($staff)->post(route('inbound.scans.store', $batch), scanPayload($label->code))->assertSessionHasErrors('code');

    expect(Box::count())->toBe(1);
});

test('inactive products and locations are rejected', function () {
    $staff = User::factory()->create();
    $label = QrLabel::factory()->create();

    $this->actingAs($staff)->post(route('inbound.scans.store', InboundBatch::factory()->create()), scanPayload($label->code, [
        'product_id' => Product::factory()->inactive()->create()->id,
        'location_id' => Location::factory()->inactive()->create()->id,
    ]))->assertSessionHasErrors(['product_id', 'location_id']);
});

test('changing batch values mid-batch only affects the following scans', function () {
    $staff = User::factory()->create();
    $batch = InboundBatch::factory()->create();
    [$first, $second] = QrLabel::factory()->count(2)->create();
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    $this->actingAs($staff)->post(route('inbound.scans.store', $batch), scanPayload($first->code, ['product_id' => $productA->id]));
    $this->actingAs($staff)->post(route('inbound.scans.store', $batch), scanPayload($second->code, ['product_id' => $productB->id]));

    expect(Box::firstWhere('qr_code', $first->code)->product_id)->toBe($productA->id)
        ->and(Box::firstWhere('qr_code', $second->code)->product_id)->toBe($productB->id);
});

test('finishing a batch shows the summary and stops further scans', function () {
    $staff = User::factory()->create();
    $batch = InboundBatch::factory()->create();
    $product = Product::factory()->create(['fish_name' => 'MB', 'grade' => 'A', 'size' => '3-5']);
    Box::factory()->count(3)->create(['inbound_batch_id' => $batch->id, 'product_id' => $product->id, 'expired_date' => '2027-01-01']);
    Box::factory()->create(['inbound_batch_id' => $batch->id, 'product_id' => $product->id, 'expired_date' => '2027-02-01']);

    $this->actingAs($staff)->post(route('inbound.finish', $batch))->assertRedirect(route('inbound.show', $batch));

    expect($batch->fresh()->isFinished())->toBeTrue()
        ->and(ActivityLog::firstWhere('action', 'inbound.finished')->new_values)->toBe(['box_count' => 4]);

    $this->actingAs($staff)->get(route('inbound.show', $batch))
        ->assertInertia(fn ($page) => $page
            ->where('boxCount', 4)
            ->where('summary', [
                ['product' => 'MB A 3-5', 'production_date' => null, 'expired_date' => '2027-01-01', 'total' => 3],
                ['product' => 'MB A 3-5', 'production_date' => null, 'expired_date' => '2027-02-01', 'total' => 1],
            ]));

    $label = QrLabel::factory()->create();
    $this->actingAs($staff)->post(route('inbound.scans.store', $batch), scanPayload($label->code))->assertSessionHasErrors('code');
});

test('owner can not do inbound', function () {
    $owner = User::factory()->owner()->create();
    $batch = InboundBatch::factory()->create();

    $this->actingAs($owner)->get('/inbound')->assertForbidden();
    $this->actingAs($owner)->post('/inbound', ['supplier_name' => 'X'])->assertForbidden();
    $this->actingAs($owner)->post(route('inbound.scans.store', $batch), [])->assertForbidden();
});

test('an empty batch can not be finished but can be cancelled, leaving a trace in the log', function () {
    $staff = User::factory()->create();
    $batch = InboundBatch::factory()->create(['supplier_name' => 'PT Laut']);

    $this->actingAs($staff)->post(route('inbound.finish', $batch))->assertSessionHasErrors('batch');
    expect($batch->fresh()->finished_at)->toBeNull();

    $this->actingAs($staff)->delete(route('inbound.destroy', $batch))->assertRedirect(route('inbound.index'));

    expect(InboundBatch::find($batch->id))->toBeNull();
    $log = ActivityLog::firstWhere('action', 'inbound.cancelled');
    expect($log->subject_id)->toBe($batch->id)
        ->and($log->old_values['supplier_name'])->toBe('PT Laut')
        ->and($log->user_id)->toBe($staff->id);
});

test('a batch that already has boxes can not be cancelled', function () {
    $staff = User::factory()->create();
    $batch = InboundBatch::factory()->create();
    Box::factory()->create(['inbound_batch_id' => $batch->id])->delete();

    $this->actingAs($staff)->delete(route('inbound.destroy', $batch))->assertSessionHasErrors('batch');

    expect(InboundBatch::find($batch->id))->not->toBeNull();
});
