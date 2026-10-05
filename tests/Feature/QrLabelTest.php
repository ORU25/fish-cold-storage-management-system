<?php

use App\Enums\QrLabelStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Box;
use App\Models\Location;
use App\Models\Product;
use App\Models\QrLabel;
use App\Models\User;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

test('admin generates stickers numbered per day and is sent to the print sheet', function () {
    $this->travelTo('2026-10-03 09:00');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/qr-labels', ['quantity' => 3])->assertRedirectContains('/print');
    $this->actingAs($admin)->post('/qr-labels', ['quantity' => 2]);

    expect(QrLabel::orderBy('code')->pluck('code')->all())->toBe([
        'DUS-261003-0001', 'DUS-261003-0002', 'DUS-261003-0003', 'DUS-261003-0004', 'DUS-261003-0005',
    ])
        ->and(QrLabel::where('status', QrLabelStatus::Available)->count())->toBe(5)
        ->and(ActivityLog::where('action', 'qr.generated')->count())->toBe(2);

    $this->travelTo('2026-10-04 09:00');
    $this->actingAs($admin)->post('/qr-labels', ['quantity' => 1]);

    expect(QrLabel::where('code', 'DUS-261004-0001')->exists())->toBeTrue();
});

test('sticker quantity is limited', function (int $quantity) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/qr-labels', ['quantity' => $quantity])->assertSessionHasErrors('quantity');
})->with([0, 101]);

test('print sheet shows every label of the batch with its QR image and can be reprinted', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post('/qr-labels', ['quantity' => 2]);
    $label = QrLabel::orderBy('code')->first();

    $this->actingAs($admin)->get(route('qr-labels.print', $label->qr_print_batch_id))
        ->assertOk()
        ->assertSee($label->code)
        ->assertSee('data:image/svg+xml;base64,', false);
});

test('each printed QR holds only its own code, not the codes printed before it', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post('/qr-labels', ['quantity' => 3]);
    $last = QrLabel::orderByDesc('code')->first();

    $labels = $this->actingAs($admin)->get(route('qr-labels.print', $last->qr_print_batch_id))->viewData('labels');

    expect($labels->last()['image'])->toBe((new QRCode(new QROptions(['quietzoneSize' => 1])))->render($last->code));
});

test('batch detail lists its own stickers, searchable by code and status, with the box a used sticker is on', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post('/qr-labels', ['quantity' => 3]);
    $this->actingAs($admin)->post('/qr-labels', ['quantity' => 1]);
    [$used, $available] = QrLabel::orderBy('code')->take(2)->get();
    $used->update(['status' => QrLabelStatus::Used, 'used_at' => now()]);
    Box::factory()->create([
        'qr_label_id' => $used->id,
        'qr_code' => $used->code,
        'product_id' => Product::factory()->create(['fish_name' => 'MB', 'grade' => 'A', 'size' => '3-5'])->id,
        'location_id' => Location::factory()->create(['name' => 'Blok A'])->id,
    ]);
    $batch = $used->qr_print_batch_id;

    $this->actingAs($admin)->get(route('qr-labels.show', $batch))
        ->assertInertia(fn ($page) => $page
            ->component('qr-labels/show')
            ->where('batch.used_count', 1)
            ->where('batch.available_count', 2)
            ->has('labels.data', 3)
            ->where('labels.data.0.code', $used->code)
            ->where('labels.data.0.box.product.display_name', 'MB A 3-5')
            ->where('labels.data.0.box.location.name', 'Blok A')
            ->where('labels.data.1.box', null));

    $this->actingAs($admin)->get(route('qr-labels.show', [$batch, 'code' => strtolower(substr($available->code, -4))]))
        ->assertInertia(fn ($page) => $page->has('labels.data', 1)->where('labels.data.0.code', $available->code));
    $this->actingAs($admin)->get(route('qr-labels.show', [$batch, 'status' => 'available']))
        ->assertInertia(fn ($page) => $page->has('labels.data', 2));
});

test('a single sticker can be reprinted unless it is void', function () {
    $admin = User::factory()->admin()->create();
    $available = QrLabel::factory()->create();
    $used = QrLabel::factory()->create(['status' => QrLabelStatus::Used]);
    $void = QrLabel::factory()->create(['status' => QrLabelStatus::Void]);

    $this->actingAs($admin)->get(route('qr-labels.print-label', $available))->assertOk()->assertSee($available->code)->assertDontSee($used->code);
    $this->actingAs($admin)->get(route('qr-labels.print-label', $used))->assertOk()->assertSee($used->code);
    $this->actingAs($admin)->get(route('qr-labels.print-label', $void))->assertNotFound();
});

test('admin voids several available stickers at once with one reason, logged per sticker', function () {
    $admin = User::factory()->admin()->create();
    $labels = QrLabel::factory()->count(3)->create();

    $this->actingAs($admin)->post('/qr-labels/void', ['ids' => $labels->pluck('id')->all(), 'reason' => 'Gulungan basah'])->assertSessionHasNoErrors()->assertSessionHas('success', '3 stiker di-void.');

    expect($labels->map->fresh()->pluck('status')->unique()->all())->toBe([QrLabelStatus::Void]);
    $logs = ActivityLog::where('action', 'qr.voided')->get();
    expect($logs)->toHaveCount(3)
        ->and($logs->pluck('reason')->unique()->all())->toBe(['Gulungan basah'])
        ->and($logs->first()->new_values)->toBe(['status' => 'void']);
});

test('a selection with a used or void sticker voids nothing, and ids and a reason are required', function () {
    $admin = User::factory()->admin()->create();
    $available = QrLabel::factory()->create();
    $used = QrLabel::factory()->create(['status' => QrLabelStatus::Used]);

    $this->actingAs($admin)->post('/qr-labels/void', ['ids' => [$available->id, $used->id], 'reason' => 'x'])
        ->assertSessionHasErrors(['ids' => "Hanya stiker available yang bisa di-void: {$used->code} (used)."]);
    $this->actingAs($admin)->post('/qr-labels/void', ['ids' => [], 'reason' => 'x'])->assertSessionHasErrors('ids');
    $this->actingAs($admin)->post('/qr-labels/void', ['ids' => [$available->id]])->assertSessionHasErrors('reason');

    expect($available->fresh()->status)->toBe(QrLabelStatus::Available)
        ->and($used->fresh()->status)->toBe(QrLabelStatus::Used)
        ->and(ActivityLog::where('action', 'qr.voided')->exists())->toBeFalse();
});

test('staff can not manage stickers', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);
    $label = QrLabel::factory()->create();

    $this->actingAs($user)->get('/qr-labels')->assertForbidden();
    $this->actingAs($user)->post('/qr-labels', ['quantity' => 1])->assertForbidden();
    $this->actingAs($user)->post('/qr-labels/void', ['ids' => [$label->id], 'reason' => 'x'])->assertForbidden();
    $this->actingAs($user)->get(route('qr-labels.print', $label->qr_print_batch_id))->assertForbidden();
    $this->actingAs($user)->get(route('qr-labels.show', $label->qr_print_batch_id))->assertForbidden();
    $this->actingAs($user)->get(route('qr-labels.print-label', $label))->assertForbidden();
})->with([Role::Staff]);
