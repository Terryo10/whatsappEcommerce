<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\DriverAuthController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Services\Delivery\DeliveryService;
use Illuminate\Support\Facades\Route;

// ── Public routes ─────────────────────────────────────────────────────────────

// Order tracking (no auth)
Route::get('/track/{token}', function (string $token, DeliveryService $service) {
    $info = $service->getTrackingInfo($token);
    return $info ? response()->json($info) : response()->json(['error' => 'Not found'], 404);
})->name('api.track');

// Product catalog (public read — no auth needed for browsing)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);

// Delivery fee + slots (public — needed at checkout before login)
Route::get('/delivery/slots', [DeliveryController::class, 'slots']);
Route::post('/delivery/calculate-fee', [DeliveryController::class, 'calculateFee']);

// ── Customer auth ─────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });
});

// ── Driver auth ───────────────────────────────────────────────────────────────
Route::prefix('driver/auth')->group(function () {
    Route::post('/login', [DriverAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [DriverAuthController::class, 'logout']);
        Route::get('/me',      [DriverAuthController::class, 'me']);
    });
});

// ── Customer protected routes ─────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Orders
    Route::post('/orders',        [OrderController::class, 'store']);
    Route::get('/orders',         [OrderController::class, 'history']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // Payment
    Route::post('/payment/ecocash',           [PaymentController::class, 'initiateEcoCash']);
    Route::post('/payment/card',              [PaymentController::class, 'cardLink']);
    Route::get('/payment/{order}/status',     [PaymentController::class, 'status']);
});

// ── Driver protected routes ───────────────────────────────────────────────────
Route::middleware('auth:sanctum')->prefix('driver')->group(function () {
    Route::get('/deliveries',                          [DriverController::class, 'deliveries']);
    Route::get('/deliveries/{delivery}',               [DriverController::class, 'show']);
    Route::patch('/deliveries/{delivery}/status',      [DriverController::class, 'updateStatus']);
    Route::post('/deliveries/{delivery}/photo',        [DriverController::class, 'uploadPhoto']);
    Route::post('/location',                           [DriverController::class, 'updateLocation']);
});
