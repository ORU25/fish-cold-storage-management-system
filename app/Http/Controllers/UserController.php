<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Users are never deleted (PRD 5.1), only deactivated.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('users/index', [
            'users' => User::orderBy('name')->get(['id', 'name', 'username', 'role', 'is_active']),
            'roles' => array_column(Role::cases(), 'value'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated())->refresh();

        ActivityLog::record('user.created', $user, newValues: $user->only('name', 'username', 'role', 'is_active'));

        return back()->with('success', 'Pengguna ditambahkan.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->except('password'));

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        ActivityLog::recordChanges('user.updated', $user);

        return back()->with('success', 'Pengguna diperbarui.');
    }
}
