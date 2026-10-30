<?php

use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Storefront sign-in. The storefront links here; Google sends the customer back to the callback.
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect']);
    Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
});
