<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\InboundBatchController;
use App\Http\Controllers\InboundCancellationController;
use App\Http\Controllers\InboundScanController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QrLabelController;
use App\Http\Controllers\StockController;
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

    Route::middleware('role:admin')->group(function () {
        Route::get('qr-labels', [QrLabelController::class, 'index'])->name('qr-labels.index');
        Route::post('qr-labels', [QrLabelController::class, 'store'])->name('qr-labels.store');
        Route::post('qr-labels/void', [QrLabelController::class, 'void'])->name('qr-labels.void');
        Route::get('qr-labels/batches/{batch}/print', [QrLabelController::class, 'print'])->name('qr-labels.print');
        Route::post('boxes/{box}/cancel-inbound', [InboundCancellationController::class, 'store'])->name('boxes.cancel-inbound');
    });

    Route::middleware('role:staff,admin')->group(function () {
        Route::resource('inbound', InboundBatchController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['inbound' => 'batch']);
        Route::post('inbound/{batch}/finish', [InboundBatchController::class, 'finish'])->name('inbound.finish');
        Route::post('inbound/{batch}/scans', [InboundScanController::class, 'store'])->name('inbound.scans.store');
    });

    Route::get('stock', [StockController::class, 'index'])
        ->middleware('role:owner,admin')
        ->name('stock.index');

    Route::middleware('role:owner')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
    });

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('role:owner,admin')
        ->name('activity-logs.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
