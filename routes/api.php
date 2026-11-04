<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| 1. Public            - anyone (shop catalogue, checkout, login)
| 2. Logged in         - customers (account, orders, addresses)
| 3. Admin only        - inventory, sales, categories, orders
*/

// ---------- 1. Public ----------
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::prefix('shop')->group(function () {
    Route::get('/settings', [CatalogController::class, 'settings']);
    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::get('/products', [CatalogController::class, 'products']);
    Route::get('/products/{slug}', [CatalogController::class, 'show']);
    Route::get('/cart', [CatalogController::class, 'cart']);

    Route::post('/orders', [CheckoutController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/orders/track', [CheckoutController::class, 'track'])->middleware('throttle:20,1');
    Route::post('/orders/pay', [CheckoutController::class, 'pay'])->middleware('throttle:20,1');
    Route::post('/orders/cancel', [CheckoutController::class, 'cancel'])->middleware('throttle:20,1');
});

// ---------- 2. Logged-in customers ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);

    Route::prefix('account')->group(function () {
        Route::get('/orders', [AccountController::class, 'orders']);
        Route::get('/orders/{id}', [AccountController::class, 'show']);
        Route::post('/orders/{id}/cancel', [AccountController::class, 'cancel']);

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
    });
});

// ---------- 3. Admin only ----------
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/kookuproducts', [ProductController::class, 'index']);
    Route::post('/kookuproducts', [ProductController::class, 'store']);
    Route::put('/kookuproducts/{id}', [ProductController::class, 'update']);
    Route::delete('/kookuproducts/{id}', [ProductController::class, 'destroy']);

    Route::post('/sales', [SalesController::class, 'store']);
    Route::get('/sales', [SalesController::class, 'index']);

    Route::prefix('admin')->group(function () {
        Route::get('/summary', [OrderController::class, 'summary']);

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::put('/payments/{id}/review', [OrderController::class, 'reviewPayment']);
    });
});
