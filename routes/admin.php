<?php

/**
 * Admin console routes under /admin.
 *
 * Auth + admin.access required for the shell; each module adds permission middleware.
 */

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::middleware('permission:products.view')->group(function () {
        Route::get('/products', [AdminController::class, 'products'])->name('products');
    });
    Route::delete('/products/{id}', [AdminController::class, 'destroyProduct'])
        ->middleware('permission:products.delete')
        ->name('products.destroy');

    Route::middleware('permission:vendors.view')->group(function () {
        Route::get('/vendors', [AdminController::class, 'vendors'])->name('vendors');
    });
    Route::post('/vendors/{id}/toggle', [AdminController::class, 'toggleVendorVerification'])
        ->middleware('permission:vendors.approve,vendors.suspend')
        ->name('vendors.toggle');

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('users');
    });
    Route::put('/users/{id}', [AdminController::class, 'updateUserRole'])
        ->middleware(['permission:users.update', 'stepup'])
        ->name('users.update');

    Route::middleware('permission:orders.view')->group(function () {
        Route::get('/orders', [AdminController::class, 'orders'])->name('orders');
    });
    Route::put('/orders/{id}', [AdminController::class, 'updateOrderStatus'])
        ->middleware('permission:orders.update')
        ->name('orders.update');
    Route::patch('/orders/{order}/payment', [AdminController::class, 'updateOrderPayment'])
        ->middleware('permission:payments.manage')
        ->name('orders.payment');
    Route::patch('/orders/{order}/items/{orderItem}/fulfillment', [AdminController::class, 'updateItemFulfillment'])
        ->middleware('permission:orders.update')
        ->name('orders.items.fulfillment');

    Route::get('/categories', [AdminController::class, 'categories'])
        ->middleware('permission:categories.view')
        ->name('categories');
    Route::get('/coupons', [AdminController::class, 'coupons'])
        ->middleware('permission:coupons.view')
        ->name('coupons');

    Route::middleware('permission:reviews.view')->group(function () {
        Route::get('/reviews', [AdminController::class, 'reviews'])->name('reviews');
    });
    Route::patch('/reviews/{review}/moderate', [AdminController::class, 'moderateReview'])
        ->middleware('permission:reviews.moderate')
        ->name('reviews.moderate');

    Route::get('/inventory', [AdminController::class, 'inventory'])
        ->middleware('permission:inventory.view')
        ->name('inventory');

    Route::get('/audit-logs', [AdminController::class, 'auditLogs'])
        ->middleware('permission:audit_logs.view')
        ->name('audit-logs');
    Route::get('/security-events', [AdminController::class, 'securityEvents'])
        ->middleware('permission:security_events.view')
        ->name('security-events');
    Route::get('/roles', [AdminController::class, 'roles'])
        ->middleware('permission:roles.view')
        ->name('roles');
});
