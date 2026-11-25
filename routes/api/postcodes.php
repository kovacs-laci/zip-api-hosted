<?php

use App\Http\Controllers\Api\PostcodeController;
use Illuminate\Support\Facades\Route; // Point to the API controller

// API Routes for Postcode

Route::prefix('postcodes')->group(function () {
    // Publicly accessible routes
    Route::get('/', [PostcodeController::class, 'index'])->name('postcodes.index');
    Route::get('/{id}', [PostcodeController::class, 'show'])->name('postcodes.show');

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [PostcodeController::class, 'store'])->name('postcodes.store');
        Route::put('/{id}', [PostcodeController::class, 'update'])->name('postcodes.update');
        Route::delete('/{id}', [PostcodeController::class, 'destroy'])->name('postcodes.destroy');
    });
});
