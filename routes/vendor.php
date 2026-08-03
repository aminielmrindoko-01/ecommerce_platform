<?php

/**
 * Vendor console routes under /vendor.
 *
 * Requires auth + vendor middleware (role=vendor with linked store).
 */

use App\Http\Controllers\Vendor\DashboardController;
use App\Http\Controllers\Vendor\OrderController;
use App\Http\Controllers\Vendor\ProductController;
use App\Http\Controllers\Vendor\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'vendor'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/items/{orderItem}/fulfillment', [OrderController::class, 'updateFulfillment'])
        ->name('orders.items.fulfillment');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/finance', [\App\Http\Controllers\Vendor\FinanceController::class, 'index'])->name('finance.index');
    Route::post('/finance/payout', [\App\Http\Controllers\Vendor\FinanceController::class, 'requestPayout'])
        ->middleware('throttle:10,1')
        ->name('finance.payout');

    Route::get('/returns', [\App\Http\Controllers\Vendor\ReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/{return}', [\App\Http\Controllers\Vendor\ReturnController::class, 'show'])->name('returns.show');
    Route::post('/returns/{return}/approve', [\App\Http\Controllers\Vendor\ReturnController::class, 'approve'])->name('returns.approve');
    Route::post('/returns/{return}/reject', [\App\Http\Controllers\Vendor\ReturnController::class, 'reject'])->name('returns.reject');
    Route::post('/returns/{return}/receive', [\App\Http\Controllers\Vendor\ReturnController::class, 'receive'])->name('returns.receive');

    Route::get('/disputes', [\App\Http\Controllers\Vendor\DisputeController::class, 'index'])->name('disputes.index');
    Route::get('/disputes/{dispute}', [\App\Http\Controllers\Vendor\DisputeController::class, 'show'])->name('disputes.show');
    Route::post('/disputes/{dispute}/respond', [\App\Http\Controllers\Vendor\DisputeController::class, 'respond'])->name('disputes.respond');
});
