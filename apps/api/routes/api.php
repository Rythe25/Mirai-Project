<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaywayCallbackController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Google sign-in itself lives in routes/web.php (/auth/google/*).
Route::get('/csrf', [AuthController::class, 'csrf']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    // Checkout needs a signed-in customer.
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:10,1');
});

// Skeleton scaffolding — remove once real endpoints replace it.
Route::get('/ping', fn () => response()->json([
    'service' => 'mirai-api',
    'db' => DB::connection()->getDatabaseName(),
    'time' => now()->toIso8601String(),
]));

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product:slug}', [ProductController::class, 'show']);

Route::get('/orders/{order:public_id}', [OrderController::class, 'show']);

Route::post('/orders/{order:public_id}/pay', [PaymentController::class, 'store'])->middleware('throttle:10,1');
Route::get('/orders/{order:public_id}/payment', [PaymentController::class, 'show']);
Route::get('/orders/{order:public_id}/invoice', [InvoiceController::class, 'show'])->middleware('throttle:30,1');

Route::post('/payway/callback', PaywayCallbackController::class);
