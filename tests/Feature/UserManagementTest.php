<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('owner can create a user', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post('/users', [
        'name' => 'Budi',
        'username' => 'budi',
        'role' => 'admin',
        'password' => 'rahasia123',
    ])->assertSessionHasNoErrors();

    $user = User::firstWhere('username', 'budi');
    expect($user->role)->toBe(Role::Admin)
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue()
        ->and(ActivityLog::where('action', 'user.created')->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('owner can change role, reset password and deactivate a user', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->create();

    $this->actingAs($owner)->put("/users/{$user->id}", [
        'name' => $user->name,
        'role' => 'admin',
        'is_active' => false,
        'password' => 'passwordbaru',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->role)->toBe(Role::Admin)
        ->and($user->is_active)->toBeFalse()
        ->and(Hash::check('passwordbaru', $user->password))->toBeTrue();

    $log = ActivityLog::firstWhere('action', 'user.updated');
    expect($log->old_values)->toMatchArray(['role' => 'staff', 'is_active' => true, 'password' => '[diubah]'])
        ->and($log->new_values)->toMatchArray(['role' => 'admin', 'is_active' => false, 'password' => '[diubah]']);
});

test('owner can not demote or deactivate themselves', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->put("/users/{$owner->id}", [
        'name' => $owner->name,
        'role' => 'admin',
        'is_active' => false,
    ])->assertSessionHasErrors(['role', 'is_active']);

    expect($owner->fresh()->role)->toBe(Role::Owner);
});

test('username must be unique', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post('/users', [
        'name' => 'Lain',
        'username' => $owner->username,
        'role' => 'staff',
        'password' => 'rahasia123',
    ])->assertSessionHasErrors('username');
});

test('non owners can not manage users', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get('/users')->assertForbidden();
    $this->actingAs($user)->post('/users', [])->assertForbidden();
    $this->actingAs($user)->put("/users/{$user->id}", [])->assertForbidden();
})->with([Role::Admin, Role::Staff]);

test('users can not be deleted', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->create();

    $this->actingAs($owner)->delete("/users/{$user->id}")->assertMethodNotAllowed();
});
