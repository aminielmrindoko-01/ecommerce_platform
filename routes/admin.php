<?php

/**
 * Admin console routes under /admin.
 *
 * Auth + admin.access required for the shell; each module adds permission middleware.
 */

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\VendorController as AdminVendorController;
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
        Route::get('/vendors', [AdminVendorController::class, 'index'])->name('vendors');
    });
    Route::post('/vendors/{vendor}/status', [AdminVendorController::class, 'transition'])
        ->middleware('permission:vendors.approve,vendors.reject,vendors.suspend,vendors.update')
        ->name('vendors.status');
    // Legacy toggle kept as thin wrapper for approve/suspend via status service.
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

    Route::get('/payments', [AdminPaymentController::class, 'index'])
        ->middleware('permission:payments.view,transactions.view')
        ->name('payments.index');
    Route::get('/payments/refunds', [AdminPaymentController::class, 'refunds'])
        ->middleware('permission:payments.view,refunds.create')
        ->name('payments.refunds');
    Route::get('/payments/reconciliations', [AdminPaymentController::class, 'reconciliations'])
        ->middleware('permission:payments.view')
        ->name('payments.reconciliations');
    Route::post('/payments/{payment}/refund', [AdminPaymentController::class, 'storeRefund'])
        ->middleware(['permission:refunds.create', 'stepup'])
        ->name('payments.refund');

    Route::get('/finance/ledger', [AdminFinanceController::class, 'ledger'])
        ->middleware('permission:ledger.view')
        ->name('finance.ledger');
    Route::get('/finance/payables', [AdminFinanceController::class, 'payables'])
        ->middleware('permission:payouts.view,finance.reports.view')
        ->name('finance.payables');
    Route::get('/finance/payouts', [AdminFinanceController::class, 'payouts'])
        ->middleware('permission:payouts.view')
        ->name('finance.payouts');
    Route::get('/finance/entitlements', [AdminFinanceController::class, 'entitlements'])
        ->middleware('permission:ledger.view,finance.reports.view')
        ->name('finance.entitlements');
    Route::get('/finance/reports', [AdminFinanceController::class, 'reports'])
        ->middleware('permission:finance.reports.view')
        ->name('finance.reports');
    Route::post('/finance/payouts/{payout}/approve', [AdminFinanceController::class, 'approvePayout'])
        ->middleware(['permission:payouts.approve,payouts.process', 'stepup'])
        ->name('finance.payouts.approve');
    Route::post('/finance/payouts/{payout}/reject', [AdminFinanceController::class, 'rejectPayout'])
        ->middleware(['permission:payouts.reject,payouts.approve', 'stepup'])
        ->name('finance.payouts.reject');
    Route::post('/finance/payouts/{payout}/process', [AdminFinanceController::class, 'processPayout'])
        ->middleware(['permission:payouts.process', 'stepup'])
        ->name('finance.payouts.process');

    // Marketplace operations (Phase 7)
    Route::get('/operations/returns', [\App\Http\Controllers\Admin\OperationsController::class, 'returns'])
        ->middleware('permission:returns.view')
        ->name('operations.returns');
    Route::get('/operations/returns/{return}', [\App\Http\Controllers\Admin\OperationsController::class, 'showReturn'])
        ->middleware('permission:returns.view')
        ->name('operations.returns.show');
    Route::post('/operations/returns/{return}/approve', [\App\Http\Controllers\Admin\OperationsController::class, 'approveReturn'])
        ->middleware('permission:returns.approve,returns.manage')
        ->name('operations.returns.approve');
    Route::post('/operations/returns/{return}/reject', [\App\Http\Controllers\Admin\OperationsController::class, 'rejectReturn'])
        ->middleware('permission:returns.approve,returns.manage')
        ->name('operations.returns.reject');
    Route::post('/operations/returns/{return}/receive', [\App\Http\Controllers\Admin\OperationsController::class, 'receiveReturn'])
        ->middleware('permission:returns.manage,returns.approve')
        ->name('operations.returns.receive');
    Route::post('/operations/returns/{return}/refund', [\App\Http\Controllers\Admin\OperationsController::class, 'refundReturn'])
        ->middleware(['permission:refunds.create', 'stepup'])
        ->name('operations.returns.refund');

    Route::get('/operations/disputes', [\App\Http\Controllers\Admin\OperationsController::class, 'disputes'])
        ->middleware('permission:disputes.view')
        ->name('operations.disputes');
    Route::get('/operations/disputes/{dispute}', [\App\Http\Controllers\Admin\OperationsController::class, 'showDispute'])
        ->middleware('permission:disputes.view')
        ->name('operations.disputes.show');
    Route::post('/operations/disputes/{dispute}/resolve', [\App\Http\Controllers\Admin\OperationsController::class, 'resolveDispute'])
        ->middleware('permission:disputes.resolve,disputes.manage')
        ->name('operations.disputes.resolve');
    Route::post('/operations/disputes/{dispute}/respond', [\App\Http\Controllers\Admin\OperationsController::class, 'respondDispute'])
        ->middleware('permission:disputes.respond,disputes.manage')
        ->name('operations.disputes.respond');

    Route::get('/operations/chargebacks', [\App\Http\Controllers\Admin\OperationsController::class, 'chargebacks'])
        ->middleware('permission:chargebacks.view')
        ->name('operations.chargebacks');
    Route::post('/operations/chargebacks', [\App\Http\Controllers\Admin\OperationsController::class, 'storeChargeback'])
        ->middleware(['permission:chargebacks.create,chargebacks.manage', 'stepup'])
        ->name('operations.chargebacks.store');
    Route::post('/operations/chargebacks/{chargeback}/status', [\App\Http\Controllers\Admin\OperationsController::class, 'updateChargeback'])
        ->middleware(['permission:chargebacks.resolve,chargebacks.manage', 'stepup'])
        ->name('operations.chargebacks.status');

    Route::get('/operations/holds', [\App\Http\Controllers\Admin\OperationsController::class, 'holds'])
        ->middleware('permission:settlement_holds.view')
        ->name('operations.holds');
    Route::post('/operations/holds/{hold}/release', [\App\Http\Controllers\Admin\OperationsController::class, 'releaseHold'])
        ->middleware(['permission:settlement_holds.manage', 'stepup'])
        ->name('operations.holds.release');

    Route::get('/operations/commission', [\App\Http\Controllers\Admin\OperationsController::class, 'commission'])
        ->middleware('permission:commission.manage,finance.reports.view')
        ->name('operations.commission');
    Route::post('/operations/commission', [\App\Http\Controllers\Admin\OperationsController::class, 'updateCommission'])
        ->middleware(['permission:commission.manage', 'stepup'])
        ->name('operations.commission.update');
    Route::post('/operations/vendors/{vendor}/financial-status', [\App\Http\Controllers\Admin\OperationsController::class, 'setVendorFinancialStatus'])
        ->middleware(['permission:vendors.suspend,payouts.process', 'stepup'])
        ->name('operations.vendors.financial-status');

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
