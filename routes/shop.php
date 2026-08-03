<?php

/**
 * Storefront + account + auth routes for SANA Market.
 *
 * Sensitive POSTs (login, register, contact, newsletter, checkout) use throttle
 * middleware to reduce brute-force and spam. Cart is session-based (guest OK);
 * checkout/wishlist/account require auth.
 */

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Payments\PesapalCallbackController;
use App\Http\Controllers\Payments\PesapalWebhookController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShopPageController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/product/{id}', [ProductController::class, 'show'])->name('products.show');
Route::post('/product/{id}/reviews', [ProductController::class, 'storeReview'])->middleware('throttle:10,1')->name('products.reviews.store');
Route::post('/product/{id}/questions', [ProductController::class, 'storeQuestion'])->middleware('throttle:10,1')->name('products.questions.store');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/products/create', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
    Route::get('/products/{id}/edit', [ProductController::class, 'edit'])->middleware('permission:products.update')->name('products.edit');
    Route::put('/products/{id}', [ProductController::class, 'update'])->middleware('permission:products.update')->name('products.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');
});

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add/{id}', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/increase/{id}', [CartController::class, 'increase'])->name('cart.increase');
Route::post('/cart/decrease/{id}', [CartController::class, 'decrease'])->name('cart.decrease');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
Route::post('/cart/save/{id}', [CartController::class, 'saveForLater'])->name('cart.save');
Route::post('/cart/move/{id}', [CartController::class, 'moveToCart'])->name('cart.move');

Route::get('/about', [ShopPageController::class, 'about'])->name('about');
Route::get('/contact', [ShopPageController::class, 'contact'])->name('contact');
Route::post('/contact', [ShopPageController::class, 'contactSubmit'])->middleware('throttle:5,1')->name('contact.submit');
Route::get('/vendors', [ShopPageController::class, 'vendors'])->name('vendors');
Route::get('/categories', [ShopPageController::class, 'categories'])->name('categories');
Route::get('/deals', [ShopPageController::class, 'deals'])->name('deals');
Route::get('/blog', [ShopPageController::class, 'blog'])->name('blog');
Route::post('/newsletter', [ShopPageController::class, 'newsletter'])->middleware('throttle:10,1')->name('newsletter.subscribe');

Route::get('/api/search/suggest', [ApiController::class, 'searchSuggest'])->name('api.search.suggest');
Route::get('/api/products/recent', [ApiController::class, 'recentProducts'])->name('api.products.recent');

Route::post('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('login.submit');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::redirect('/profile', '/account')->name('profile');

    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::get('/account/orders', [AccountController::class, 'orders'])->name('account.orders');
    Route::get('/account/orders/{order}', [AccountController::class, 'showOrder'])->name('account.orders.show');
    Route::get('/account/addresses', [AccountController::class, 'addresses'])->name('account.addresses');
    Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
    Route::delete('/account/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('account.addresses.destroy');
    Route::get('/account/security', [AccountController::class, 'security'])->name('account.security');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::get('/account/notifications', [AccountController::class, 'notifications'])->name('account.notifications');
    Route::post('/account/notifications/{notification}/read', [AccountController::class, 'markNotificationRead'])
        ->name('account.notifications.read');
    Route::post('/account/notifications/read-all', [AccountController::class, 'markAllNotificationsRead'])
        ->name('account.notifications.readAll');
    Route::get('/account/wishlist', [AccountController::class, 'wishlist'])->name('account.wishlist');

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/{id}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'place'])->middleware('throttle:10,1')->name('checkout.place');
    Route::get('/checkout/confirmation/{order}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
});

/*
|--------------------------------------------------------------------------
| PesaPal sandbox callback / IPN (Phase 8A)
|--------------------------------------------------------------------------
| Browser return is NOT proof of payment. IPN/callback must verify independently
| via GetTransactionStatus and only then call PaymentService.
*/
Route::match(['get', 'post'], '/api/payments/pesapal/ipn', PesapalWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('payments.pesapal.ipn');

Route::get('/payments/pesapal/callback', PesapalCallbackController::class)
    ->middleware('throttle:60,1')
    ->name('payments.pesapal.callback');
