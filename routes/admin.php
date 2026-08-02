<?php

/**
 * Admin console routes under /admin.
 *
 * Auth + admin middleware required; non-admin authenticated users receive 403.
 */

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/products', [AdminController::class, 'products'])->name('products');
    Route::delete('/products/{id}', [AdminController::class, 'destroyProduct'])->name('products.destroy');
    Route::get('/vendors', [AdminController::class, 'vendors'])->name('vendors');
    Route::post('/vendors/{id}/toggle', [AdminController::class, 'toggleVendorVerification'])->name('vendors.toggle');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::put('/users/{id}', [AdminController::class, 'updateUserRole'])->name('users.update');
    Route::get('/orders', [AdminController::class, 'orders'])->name('orders');
    Route::put('/orders/{id}', [AdminController::class, 'updateOrderStatus'])->name('orders.update');
    Route::patch('/orders/{order}/items/{orderItem}/fulfillment', [AdminController::class, 'updateItemFulfillment'])
        ->name('orders.items.fulfillment');
    Route::get('/categories', [AdminController::class, 'categories'])->name('categories');
    Route::get('/coupons', [AdminController::class, 'coupons'])->name('coupons');
    Route::get('/reviews', [AdminController::class, 'reviews'])->name('reviews');
    Route::get('/inventory', [AdminController::class, 'inventory'])->name('inventory');
});
