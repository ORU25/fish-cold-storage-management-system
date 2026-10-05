<?php

use App\Enums\AdjustmentStatus;
use App\Enums\BoxStatus;
use App\Models\ActivityLog;
use App\Models\Adjustment;
use App\Models\Box;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('admin requests an adjustment: the box waits for the owner and the request is logged in its history', function () {
    Storage::fake();
    $admin = User::factory()->admin()->create();
    $box = Box::factory()->create();

    $this->actingAs($admin)->post(route('adjustments.store', $box), ['type' => 'damaged'])->assertSessionHasErrors('reason');

    $this->actingAs($admin)->post(route('adjustments.store', $box), [
        'type' => 'damaged',
        'reason' => 'Dus sobek, ikan mencair',
        'photo' => UploadedFile::fake()->image('dus.jpg'),
    ])->assertSessionHasNoErrors();

    $adjustment = Adjustment::sole();
    expect($box->fresh()->status)->toBe(BoxStatus::PendingAdjustment)
        ->and($adjustment)->status->toBe(AdjustmentStatus::Pending)->requested_by->toBe($admin->id)
        ->and(ActivityLog::firstWhere('action', 'adjustment.requested'))->box_id->toBe($box->id)->reason->toBe('Dus sobek, ikan mencair');
    Storage::assertExists($adjustment->photo_path);

    $this->actingAs($admin)->get(route('adjustments.photo', $adjustment))->assertOk();
    $this->actingAs($admin)->get(route('boxes.show', $box))->assertInertia(fn ($page) => $page->where('pendingAdjustment.id', $adjustment->id));
});

test('only boxes in the warehouse can be requested, so a box never has two pending requests', function (BoxStatus $status) {
    $admin = User::factory()->admin()->create();
    $box = Box::factory()->create(['status' => $status]);

    $this->actingAs($admin)->post(route('adjustments.store', $box), ['type' => 'lost', 'reason' => 'Tidak ditemukan'])
        ->assertSessionHasErrors('reason');

    expect(Adjustment::count())->toBe(0)->and($box->fresh()->status)->toBe($status);
})->with([BoxStatus::PendingAdjustment, BoxStatus::Outbound, BoxStatus::Lost]);

test('staff can not request and admin can not decide', function () {
    $adjustment = Adjustment::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('adjustments.store', Box::factory()->create()), ['type' => 'lost', 'reason' => 'x'])->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('adjustments.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post(route('adjustments.approve', $adjustment))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post(route('adjustments.reject', $adjustment))->assertForbidden();

    expect($adjustment->fresh()->status)->toBe(AdjustmentStatus::Pending);
});

test('owner approves: the box becomes lost or damaged', function (string $type, BoxStatus $expected) {
    $owner = User::factory()->owner()->create();
    $adjustment = Adjustment::factory()->create(['type' => $type]);

    $this->actingAs($owner)->post(route('adjustments.approve', $adjustment), ['decision_note' => 'Sudah dicek'])->assertSessionHasNoErrors();

    expect($adjustment->fresh())->status->toBe(AdjustmentStatus::Approved)->decided_by->toBe($owner->id)->decision_note->toBe('Sudah dicek')
        ->and($adjustment->box->fresh()->status)->toBe($expected)
        ->and(ActivityLog::firstWhere('action', 'adjustment.approved'))->box_id->toBe($adjustment->box_id);
})->with([['lost', BoxStatus::Lost], ['damaged', BoxStatus::Damaged]]);

test('owner rejects: the box is back in the warehouse, and a decided request can not be decided again', function () {
    $owner = User::factory()->owner()->create();
    $adjustment = Adjustment::factory()->create();

    $this->actingAs($owner)->post(route('adjustments.reject', $adjustment))->assertSessionHasNoErrors();

    expect($adjustment->fresh()->status)->toBe(AdjustmentStatus::Rejected)
        ->and($adjustment->box->fresh()->status)->toBe(BoxStatus::InWarehouse)
        ->and(ActivityLog::where('action', 'adjustment.rejected')->exists())->toBeTrue();

    $this->actingAs($owner)->post(route('adjustments.approve', $adjustment))->assertSessionHasErrors('decision_note');

    expect($adjustment->fresh()->status)->toBe(AdjustmentStatus::Rejected)
        ->and($adjustment->box->fresh()->status)->toBe(BoxStatus::InWarehouse);
});

test('the list shows pending requests by default', function () {
    Adjustment::factory()->create();
    Adjustment::factory()->create(['status' => AdjustmentStatus::Rejected]);

    $this->actingAs(User::factory()->admin()->create())->get(route('adjustments.index'))
        ->assertInertia(fn ($page) => $page->component('adjustments/index')->has('adjustments.data', 1)->where('filters.status', 'pending'));
});
