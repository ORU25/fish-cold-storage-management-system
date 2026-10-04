<?php

use App\Enums\BoxStatus;
use App\Enums\QrLabelStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\InboundBatch;
use App\Models\Location;
use App\Models\User;

test('admin cancels a wrong inbound scan: box leaves stock, sticker is freed and the cancellation is logged', function () {
    $admin = User::factory()->admin()->create();
    $box = Box::factory()->create();

    $this->actingAs($admin)->post(route('boxes.cancel-inbound', $box), ['reason' => 'Salah masuk batch'])->assertSessionHasNoErrors();

    expect(Box::find($box->id))->toBeNull()
        ->and(Box::withTrashed()->find($box->id)->trashed())->toBeTrue()
        ->and($box->qrLabel->fresh())->status->toBe(QrLabelStatus::Available)->used_at->toBeNull();

    $log = ActivityLog::firstWhere('action', 'box.inbound_cancelled');
    expect($log)
        ->box_id->toBe($box->id)
        ->user_id->toBe($admin->id)
        ->reason->toBe('Salah masuk batch')
        ->and($log->old_values['qr_code'])->toBe($box->qr_code);

    $this->actingAs($admin)->get('/stock')->assertInertia(fn ($page) => $page->where('boxes.total', 0));
});

test('a cancelled sticker can be scanned again into the right batch', function () {
    $admin = User::factory()->admin()->create();
    $box = Box::factory()->create();
    $rightBatch = InboundBatch::factory()->create();

    $this->actingAs($admin)->post(route('boxes.cancel-inbound', $box), ['reason' => 'Salah batch']);
    $this->actingAs($admin)->post(route('inbound.scans.store', $rightBatch), [
        'code' => $box->qr_code,
        'product_id' => $box->product_id,
        'location_id' => Location::factory()->create()->id,
        'expired_date' => '2027-01-01',
    ])->assertSessionHasNoErrors();

    expect(Box::sole()->inbound_batch_id)->toBe($rightBatch->id)
        ->and(Box::withTrashed()->where('qr_code', $box->qr_code)->count())->toBe(2);
});

test('scans in a finished batch can not be cancelled', function () {
    $box = Box::factory()->create(['inbound_batch_id' => InboundBatch::factory()->create(['finished_at' => now()])->id]);

    $this->actingAs(User::factory()->admin()->create())->post(route('boxes.cancel-inbound', $box), ['reason' => 'Salah batch'])
        ->assertSessionHasErrors(['reason' => 'Batch sudah ditutup, scan masuknya tidak bisa dibatalkan. Gunakan revisi data atau adjustment.']);

    expect(Box::find($box->id))->not->toBeNull()
        ->and($box->qrLabel->fresh()->status)->toBe(QrLabelStatus::Used)
        ->and(ActivityLog::where('action', 'box.inbound_cancelled')->exists())->toBeFalse();
});

test('a reason is required', function () {
    $box = Box::factory()->create();

    $this->actingAs(User::factory()->admin()->create())->post(route('boxes.cancel-inbound', $box), [])->assertSessionHasErrors('reason');

    expect(Box::find($box->id))->not->toBeNull();
});

test('boxes that are no longer in the warehouse can not be cancelled', function (BoxStatus $status) {
    $box = Box::factory()->create(['status' => $status]);

    $this->actingAs(User::factory()->admin()->create())->post(route('boxes.cancel-inbound', $box), ['reason' => 'x'])->assertSessionHasErrors('reason');

    expect(Box::find($box->id))->not->toBeNull()
        ->and($box->qrLabel->fresh()->status)->toBe(QrLabelStatus::Used);
})->with([BoxStatus::Outbound, BoxStatus::PendingAdjustment, BoxStatus::Lost, BoxStatus::Damaged]);

test('staff can not cancel an inbound scan', function (Role $role) {
    $box = Box::factory()->create();

    $this->actingAs(User::factory()->create(['role' => $role]))->post(route('boxes.cancel-inbound', $box), ['reason' => 'x'])->assertForbidden();

    expect(Box::find($box->id))->not->toBeNull();
})->with([Role::Staff]);
