<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/register/verify', [AuthController::class, 'verifyRegistration'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

/**
 * @api {get} /api/user Get authenticated user
 * @apiName GetAuthenticatedUser
 * @apiGroup Authentication
 * @apiVersion 1.0.0
 * @apiDescription Returns the user associated with the supplied Sanctum token.
 * @apiHeader {String} [Accept] Request JSON responses with application/json.
 * @apiHeader {String} Authorization Bearer access token (for example: Bearer 1|your-token).
 *
 * @apiExample {curl} Get authenticated user
 * curl -X GET "https://kovacslaci.hu/zip-api/api/user" \
 *   -H "Accept: application/json" \
 *   -H "Authorization: Bearer 1|your-token"
 *
 * @apiHeaderExample {json} Request headers
 * {
 *   "Accept": "application/json",
 *   "Authorization": "Bearer 1|your-token"
 * }
 *
 * @apiSuccess (200) {Number} id User ID.
 * @apiSuccess (200) {String} name User's name.
 * @apiSuccess (200) {String} email User's email address.
 * @apiSuccess (200) {String} email_verified_at Email verification timestamp; may be null.
 * @apiSuccess (200) {String} created_at Account creation timestamp.
 * @apiSuccess (200) {String} updated_at Account update timestamp.
 *
 * @apiSuccessExample {json} Success
 * {
 *   "id": 42,
 *   "name": "Test User",
 *   "email": "user@example.com",
 *   "email_verified_at": "2026-11-26T17:00:00.000000Z",
 *   "created_at": "2026-11-26T17:00:00.000000Z",
 *   "updated_at": "2026-11-26T17:00:00.000000Z"
 * }
 *
 * @apiError (401) Unauthorized Missing, invalid, or expired bearer token.
 *
 * @apiErrorExample {json} Unauthorized
 * {
 *   "message": "Unauthenticated."
 * }
 */
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
require base_path('routes/api/postcodes.php');
require base_path('routes/api/cities.php');
require base_path('routes/api/counties.php');
