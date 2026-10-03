<?php

use App\Enums\QrLabelStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\QrLabel;
use App\Models\User;

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
})->with([0, 1001]);

test('print sheet shows every label of the batch with its QR image and can be reprinted', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post('/qr-labels', ['quantity' => 2]);
    $label = QrLabel::orderBy('code')->first();

    $this->actingAs($admin)->get(route('qr-labels.print', $label->qr_print_batch_id))
        ->assertOk()
        ->assertSee($label->code)
        ->assertSee('data:image/svg+xml;base64,', false);
});

test('admin can void an available sticker with a reason', function () {
    $admin = User::factory()->admin()->create();
    $label = QrLabel::factory()->create();

    $this->actingAs($admin)->post('/qr-labels/void', ['code' => strtolower($label->code), 'reason' => 'Sobek'])->assertSessionHasNoErrors();

    expect($label->fresh()->status)->toBe(QrLabelStatus::Void);
    $log = ActivityLog::firstWhere('action', 'qr.voided');
    expect($log->reason)->toBe('Sobek')
        ->and($log->new_values)->toBe(['status' => 'void']);
});

test('used or unknown stickers can not be voided and a reason is required', function () {
    $admin = User::factory()->admin()->create();
    $used = QrLabel::factory()->create(['status' => QrLabelStatus::Used]);

    $this->actingAs($admin)->post('/qr-labels/void', ['code' => $used->code, 'reason' => 'x'])->assertSessionHasErrors('code');
    $this->actingAs($admin)->post('/qr-labels/void', ['code' => 'DUS-000000-0000', 'reason' => 'x'])->assertSessionHasErrors('code');
    $this->actingAs($admin)->post('/qr-labels/void', ['code' => $used->code])->assertSessionHasErrors('reason');

    expect($used->fresh()->status)->toBe(QrLabelStatus::Used);
});

test('only admin can manage stickers', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);
    $label = QrLabel::factory()->create();

    $this->actingAs($user)->get('/qr-labels')->assertForbidden();
    $this->actingAs($user)->post('/qr-labels', ['quantity' => 1])->assertForbidden();
    $this->actingAs($user)->post('/qr-labels/void', ['code' => $label->code, 'reason' => 'x'])->assertForbidden();
    $this->actingAs($user)->get(route('qr-labels.print', $label->qr_print_batch_id))->assertForbidden();
})->with([Role::Owner, Role::Staff]);
