<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WeatherReadingController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CacheController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-mfa', [AuthController::class, 'verifyMfa']);
});


Route::middleware(['auth:sanctum', 'mfa.verified'])->group(function () {

Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware(['cache.response'])->group(function () {

Route::prefix('weather')->group(function () {

    Route::get('/all', [WeatherReadingController::class, 'index']);
    Route::get('/city/{cityCode}', [WeatherReadingController::class, 'show']);
    Route::post('/update-all', [WeatherReadingController::class, 'updateAll']);
    Route::get('/cached', [WeatherReadingController::class, 'getCachedWeather']);
    Route::post('/city/{cityCode}/detailed', [WeatherReadingController::class, 'detailed']);
    Route::post('/top-comfortable', [WeatherReadingController::class, 'topComfortable']);
    Route::get('/sorted-by-comfort', [CityController::class, 'sortedByComfort']);
});

});

});

Route::prefix('cities')->group(function () {
    // Get all cities
    Route::get('/', [CityController::class, 'index']);

});

// Cache management (protected)
    Route::prefix('cache')->group(function () {
        Route::post('/clear', [CacheController::class, 'clear']);
        Route::post('/clear-route', [CacheController::class, 'clearRoute']);
    });