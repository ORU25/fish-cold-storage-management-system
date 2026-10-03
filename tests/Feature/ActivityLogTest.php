<?php

use App\Models\ActivityLog;
use App\Models\User;

test('activity logs can not be updated or deleted', function () {
    $log = ActivityLog::record('login');

    expect(fn () => $log->update(['action' => 'hapus']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class)
        ->and($log->fresh()->action)->toBe('login');
});

test('owner can filter logs by user, action and date', function () {
    $owner = User::factory()->owner()->create();
    $staff = User::factory()->create();
    ActivityLog::record('login', user: $staff);
    ActivityLog::record('logout', user: $staff);
    ActivityLog::record('login', user: $owner);

    $this->actingAs($owner)
        ->get('/activity-logs?'.http_build_query(['user_id' => $staff->id, 'action' => 'login', 'date_from' => today()->toDateString()]))
        ->assertInertia(fn ($page) => $page
            ->component('activity-logs/index')
            ->has('logs.data', 1)
            ->where('logs.data.0.user_id', $staff->id)
            ->where('logs.data.0.action', 'login'));

    $this->actingAs($owner)
        ->get('/activity-logs?date_to='.today()->subDay()->toDateString())
        ->assertInertia(fn ($page) => $page->has('logs.data', 0));
});

test('owner and admin can view activity logs but staff can not', function () {
    $this->actingAs(User::factory()->owner()->create())->get('/activity-logs')->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get('/activity-logs')->assertOk();
    $this->actingAs(User::factory()->create())->get('/activity-logs')->assertForbidden();
});
