<?php

use App\Http\Controllers\Api\CountyController;
use Illuminate\Support\Facades\Route; // Point to the API controller

// API Routes for County

Route::prefix('counties')->group(function () {
    // Publicly accessible routes
    Route::get('/', [CountyController::class, 'index'])->name('counties.index');
    Route::get('/{id}', [CountyController::class, 'show'])->name('counties.show');

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [CountyController::class, 'store'])->name('counties.store');
        Route::put('/{id}', [CountyController::class, 'update'])->name('counties.update');
        Route::delete('/{id}', [CountyController::class, 'destroy'])->name('counties.destroy');
    });
});
