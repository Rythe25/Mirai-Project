<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Skeleton scaffolding — remove once real endpoints replace it.
Route::get('/ping', fn () => response()->json([
    'service' => 'mirai-computer-api',
    'db' => DB::connection()->getDatabaseName(),
    'time' => now()->toIso8601String(),
]));
