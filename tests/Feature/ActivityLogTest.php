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

test('date filters include the whole selected days', function () {
    $owner = User::factory()->owner()->create();
    $this->travelTo('2026-10-02 23:59:59');
    ActivityLog::record('before', user: $owner);
    $this->travelTo('2026-10-03 00:00:00');
    ActivityLog::record('first', user: $owner);
    $this->travelTo('2026-10-03 23:59:59');
    ActivityLog::record('last', user: $owner);
    $this->travelTo('2026-10-04 00:00:00');
    ActivityLog::record('after', user: $owner);

    $this->actingAs($owner)
        ->get('/activity-logs?date_from=2026-10-03&date_to=2026-10-03')
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 2)
            ->where('logs.data.0.action', 'last')
            ->where('logs.data.1.action', 'first'));
});

test('logs are shown 50 per page, newest first', function () {
    $owner = User::factory()->owner()->create();
    foreach (range(1, 51) as $number) {
        ActivityLog::record("action-{$number}", user: $owner);
    }

    $this->actingAs($owner)->get('/activity-logs')
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 50)
            ->where('logs.data.0.action', 'action-51')
            ->where('logs.prev_page_url', null)
            ->whereNot('logs.next_page_url', null));

    $this->actingAs($owner)->get('/activity-logs?page=2')
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'action-1')
            ->where('logs.next_page_url', null));
});
