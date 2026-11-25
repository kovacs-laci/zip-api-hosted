<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/register/verify', [AuthController::class, 'verifyRegistration'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
require base_path('routes/api/postcodes.php');
require base_path('routes/api/cities.php');
require base_path('routes/api/counties.php');
