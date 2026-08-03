<?php

/**
 * Admin console routes under /admin.
 *
 * Auth + admin.access required for the shell; each module adds permission middleware.
 */

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    // Products — static paths before {product}
    Route::get('/products', [AdminProductController::class, 'index'])
        ->middleware('permission:products.view')
        ->name('products.index');
    Route::get('/products/create', [AdminProductController::class, 'create'])
        ->middleware('permission:products.create')
        ->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])
        ->middleware('permission:products.create')
        ->name('products.store');
    Route::get('/products/{product}', [AdminProductController::class, 'show'])
        ->middleware('permission:products.view')
        ->name('products.show');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])
        ->middleware('permission:products.update')
        ->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])
        ->middleware('permission:products.update')
        ->name('products.update');
    Route::post('/products/{product}/publish', [AdminProductController::class, 'publish'])
        ->middleware('permission:products.publish')
        ->name('products.publish');
    Route::post('/products/{product}/unpublish', [AdminProductController::class, 'unpublish'])
        ->middleware('permission:products.unpublish')
        ->name('products.unpublish');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])
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

    // Categories
    Route::get('/categories', [AdminCategoryController::class, 'index'])
        ->middleware('permission:categories.view')
        ->name('categories.index');
    Route::get('/categories/create', [AdminCategoryController::class, 'create'])
        ->middleware('permission:categories.create')
        ->name('categories.create');
    Route::post('/categories', [AdminCategoryController::class, 'store'])
        ->middleware('permission:categories.create')
        ->name('categories.store');
    Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])
        ->middleware('permission:categories.update')
        ->name('categories.edit');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])
        ->middleware('permission:categories.update')
        ->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])
        ->middleware('permission:categories.delete')
        ->name('categories.destroy');

    Route::get('/coupons', [AdminController::class, 'coupons'])
        ->middleware('permission:coupons.view')
        ->name('coupons');

    Route::middleware('permission:reviews.view')->group(function () {
        Route::get('/reviews', [AdminController::class, 'reviews'])->name('reviews');
    });
    Route::patch('/reviews/{review}/moderate', [AdminController::class, 'moderateReview'])
        ->middleware('permission:reviews.moderate')
        ->name('reviews.moderate');

    // Inventory
    Route::get('/inventory', [AdminInventoryController::class, 'index'])
        ->middleware('permission:inventory.view')
        ->name('inventory.index');
    Route::get('/inventory/history', [AdminInventoryController::class, 'history'])
        ->middleware('permission:inventory.history')
        ->name('inventory.history');
    Route::post('/inventory/{product}/adjust', [AdminInventoryController::class, 'adjust'])
        ->middleware('permission:inventory.adjust')
        ->name('inventory.adjust');

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
