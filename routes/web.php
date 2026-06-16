<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
use App\Http\Controllers\ProductController;

Route::post('/products', [ProductController::class, 'store']);
use App\Models\Product;

Route::get('/products', function () {
    $products = Product::with('vendor')->latest()->get();
    return view('products.index', compact('products'));
});
Route::get('/', function () {
    return view('home');
});
Route::get('/products', function () {
    return view('products');
});
Route::get('/product/{id}', function ($id) {
    $product = \App\Models\Product::with('vendor')->findOrFail($id);
    return view('product-detail', compact('product'));
});
Route::get('/cart', function () {
    return view('cart');
});
use App\Http\Controllers\CartController;

Route::get('/cart/add/{id}', [CartController::class, 'add']);
Route::get('/cart', [CartController::class, 'index']);
Route::get('/cart/remove/{id}', [CartController::class, 'remove']);
Route::get('/cart/increase/{id}', [CartController::class, 'increase']);
Route::get('/cart/decrease/{id}', [CartController::class, 'decrease']);
Route::get('/cart', [CartController::class, 'index']);
Route::get('/about', function () {
    return view('about');
});
Route::view('/about', 'about');
Route::view('/contact', 'contact');
Route::view('/vendors', 'vendors');
Route::view('/deals', 'deals');
Route::view('/blog', 'blog');
Route::view('/login', 'login');
Route::view('/register', 'register');
Route::view('/categories', 'categories');
use App\Http\Controllers\AuthController;

Route::get('/login', function () {
    return view('login');
});

Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', function () {
    return view('register');
Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout');
});

Route::post('/register', [AuthController::class, 'register']);
Route::get('/categories', function () {
    return view('categories');
});
Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout');
Route::middleware('auth')->group(function(){

    Route::get('/profile', function(){

        return view('profile');

    });


    Route::get('/checkout', function(){

        return view('checkout');

    });


});