<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/settings/profile');

    $response->assertOk();
});

test('profile name can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->name)->toBe('Test User');
});

test('users can not delete their own account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/settings/profile')->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull();
});
