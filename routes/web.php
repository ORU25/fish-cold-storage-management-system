<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::resource('products', ProductController::class)
        ->only(['index', 'store', 'update'])
        ->middleware('role:owner,admin');

    Route::resource('locations', LocationController::class)
        ->only(['index', 'store', 'update'])
        ->middleware('role:admin');

    Route::middleware('role:owner')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
    });

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('role:owner,admin')
        ->name('activity-logs.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
