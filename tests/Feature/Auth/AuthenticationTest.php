<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Support\Facades\Http;

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

test('with Turnstile enabled, a token Cloudflare accepts lets the user in', function () {
    config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => 'secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    $user = User::factory()->create();

    $this->post('/login', ['username' => $user->username, 'password' => 'password', 'turnstile_token' => 'token-ok']);

    $this->assertAuthenticated();
    Http::assertSent(fn ($request) => $request['secret'] === 'secret' && $request['response'] === 'token-ok');
});

test('with Turnstile enabled, a rejected or missing token blocks the login', function () {
    config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => 'secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
    $user = User::factory()->create();

    $this->post('/login', ['username' => $user->username, 'password' => 'password', 'turnstile_token' => 'token-bad'])
        ->assertSessionHasErrors('turnstile_token');
    $this->assertGuest();
    Http::assertSentCount(1);

    $this->post('/login', ['username' => $user->username, 'password' => 'password'])
        ->assertSessionHasErrors(['turnstile_token' => 'Selesaikan verifikasi keamanan terlebih dahulu.']);
    $this->assertGuest();
    Http::assertSentCount(1);
});

test('with Turnstile disabled, the login page gets the demo key and Cloudflare is never called', function () {
    Http::fake();
    $user = User::factory()->create();

    $this->get('/login')->assertInertia(fn ($page) => $page->where('turnstile', ['siteKey' => Turnstile::TEST_SITE_KEY, 'required' => false]));
    $this->post('/login', ['username' => $user->username, 'password' => 'password']);

    $this->assertAuthenticated();
    Http::assertNothingSent();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'username' => $user->username,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['username' => 'Username atau password salah.']);

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
