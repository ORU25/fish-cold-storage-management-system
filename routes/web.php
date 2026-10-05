<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\BoxController;
use App\Http\Controllers\BoxMoveController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboundBatchController;
use App\Http\Controllers\InboundCancellationController;
use App\Http\Controllers\InboundScanController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\OutboundController;
use App\Http\Controllers\OutboundOrderController;
use App\Http\Controllers\OutboundScanCancellationController;
use App\Http\Controllers\OutboundScanController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QrLabelController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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
        Route::get('qr-labels/batches/{batch}', [QrLabelController::class, 'show'])->name('qr-labels.show');
        Route::get('qr-labels/batches/{batch}/print', [QrLabelController::class, 'print'])->name('qr-labels.print');
        Route::get('qr-labels/{label}/print', [QrLabelController::class, 'printLabel'])->name('qr-labels.print-label');
        Route::post('boxes/{box}/cancel-inbound', [InboundCancellationController::class, 'store'])->name('boxes.cancel-inbound');
        Route::put('boxes/{box}', [BoxController::class, 'update'])->name('boxes.update');
        Route::post('boxes/{box}/move', [BoxController::class, 'move'])->name('boxes.move');
        Route::post('boxes/{box}/adjustments', [AdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('box-moves', [BoxMoveController::class, 'index'])->name('box-moves.index');
        Route::post('box-moves', [BoxMoveController::class, 'store'])->name('box-moves.store');
        Route::post('box-moves/{log}/cancel', [BoxMoveController::class, 'cancel'])->name('box-moves.cancel');

        Route::resource('orders', OutboundOrderController::class)->except(['destroy']);
        Route::post('orders/{order}/open', [OutboundOrderController::class, 'open'])->name('orders.open');
        Route::post('orders/{order}/cancel', [OutboundOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/complete', [OutboundOrderController::class, 'complete'])->name('orders.complete');
        Route::post('outbound-scans/{scan}/cancel', [OutboundScanCancellationController::class, 'store'])->name('outbound-scans.cancel');
    });

    Route::middleware('role:staff,admin')->group(function () {
        Route::resource('inbound', InboundBatchController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['inbound' => 'batch']);
        Route::post('inbound/{batch}/finish', [InboundBatchController::class, 'finish'])->name('inbound.finish');
        Route::post('inbound/{batch}/scans', [InboundScanController::class, 'store'])->name('inbound.scans.store');

        Route::get('outbound', [OutboundController::class, 'index'])->name('outbound.index');
        Route::get('outbound/{order}', [OutboundController::class, 'show'])->name('outbound.show');
        Route::post('outbound/{order}/scans', [OutboundScanController::class, 'store'])->name('outbound.scans.store');
    });

    Route::get('stock', [StockController::class, 'index'])
        ->middleware('role:owner,admin')
        ->name('stock.index');

    Route::get('boxes/{box}', [BoxController::class, 'show'])
        ->middleware('role:owner,admin')
        ->name('boxes.show');

    Route::middleware('role:owner')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
        Route::post('adjustments/{adjustment}/approve', [AdjustmentController::class, 'approve'])->name('adjustments.approve');
        Route::post('adjustments/{adjustment}/reject', [AdjustmentController::class, 'reject'])->name('adjustments.reject');
    });

    Route::middleware('role:owner,admin')->group(function () {
        Route::get('adjustments', [AdjustmentController::class, 'index'])->name('adjustments.index');
        Route::get('adjustments/{adjustment}/photo', [AdjustmentController::class, 'photo'])->name('adjustments.photo');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('role:owner,admin')
        ->name('activity-logs.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
