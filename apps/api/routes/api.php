<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaywayCallbackController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Skeleton scaffolding — remove once real endpoints replace it.
Route::get('/ping', fn () => response()->json([
    'service' => 'mirai-api',
    'db' => DB::connection()->getDatabaseName(),
    'time' => now()->toIso8601String(),
]));

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product:slug}', [ProductController::class, 'show']);

Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:10,1');
Route::get('/orders/{order:public_id}', [OrderController::class, 'show']);

Route::post('/orders/{order:public_id}/pay', [PaymentController::class, 'store'])->middleware('throttle:10,1');
Route::get('/orders/{order:public_id}/payment', [PaymentController::class, 'show']);

Route::post('/payway/callback', PaywayCallbackController::class);
