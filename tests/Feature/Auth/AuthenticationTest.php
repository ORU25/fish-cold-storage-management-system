<?php

use App\Models\ActivityLog;
use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using their username', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    expect(ActivityLog::where('action', 'user.login')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'username' => $user->username,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('inactive users can not authenticate', function () {
    $user = User::factory()->inactive()->create();

    $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('deactivated users are logged out on their next request', function () {
    $user = User::factory()->inactive()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
    expect(ActivityLog::where('action', 'user.logout')->where('user_id', $user->id)->exists())->toBeTrue();
});
