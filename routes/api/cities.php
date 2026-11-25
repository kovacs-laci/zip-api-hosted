<?php

use App\Http\Controllers\Api\CityController;
use Illuminate\Support\Facades\Route; // Point to the API controller

// API Routes for City

Route::prefix('cities')->group(function () {
    // Publicly accessible routes
    Route::get('/', [CityController::class, 'index'])->name('cities.index');
    Route::get('/{id}', [CityController::class, 'show'])->name('cities.show');

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [CityController::class, 'store'])->name('cities.store');
        Route::put('/{id}', [CityController::class, 'update'])->name('cities.update');
        Route::delete('/{id}', [CityController::class, 'destroy'])->name('cities.destroy');
    });
});
