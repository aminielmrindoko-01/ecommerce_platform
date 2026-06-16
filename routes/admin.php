<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [App\Http\Controllers\AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/products', [App\Http\Controllers\AdminController::class, 'products'])->name('products');
    Route::delete('/products/{id}', [App\Http\Controllers\AdminController::class, 'destroyProduct'])->name('products.destroy');
    Route::get('/vendors', [App\Http\Controllers\AdminController::class, 'vendors'])->name('vendors');
    Route::post('/vendors/{id}/toggle', [App\Http\Controllers\AdminController::class, 'toggleVendorVerification'])->name('vendors.toggle');
    Route::get('/users', [App\Http\Controllers\AdminController::class, 'users'])->name('users');
    Route::put('/users/{id}', [App\Http\Controllers\AdminController::class, 'updateUserRole'])->name('users.update');
    Route::get('/orders', [App\Http\Controllers\AdminController::class, 'orders'])->name('orders');
    Route::put('/orders/{id}', [App\Http\Controllers\AdminController::class, 'updateOrderStatus'])->name('orders.update');
});
