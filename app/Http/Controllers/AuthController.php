<?php

namespace App\Http\Controllers;

use App\Mail\RegistrationOtpMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * @api {post} /api/register Register and send an email verification code
     * @apiName Register
     * @apiGroup Authentication
     * @apiVersion 1.0.0
     * @apiDescription Starts registration by emailing a six-digit OTP. The user
     * account is created only after the code is verified. The OTP expires in
     * 10 minutes. A maximum of five registration requests per minute is allowed.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Content-Type Send the request body as application/json.
     * @apiBody {String} name User's name.
     * @apiBody {String} email User's unique email address.
     * @apiBody {String} password Password (minimum 8 characters).
     * @apiBody {String} password_confirmation Must match password.
     *
     * @apiExample {curl} Register
     * curl -X POST "https://kovacslaci.hu/zip-api/api/register" \
     *   -H "Accept: application/json" \
     *   -H "Content-Type: application/json" \
     *   -d '{"name":"Test User","email":"user@example.com","password":"StrongPass123!","password_confirmation":"StrongPass123!"}'
     *
     * @apiHeaderExample {json} Request headers
     * {
     *   "Accept": "application/json",
     *   "Content-Type": "application/json"
     * }
     *
     * @apiParamExample {json} Request body
     * {
     *   "name": "Test User",
     *   "email": "user@example.com",
     *   "password": "StrongPass123!",
     *   "password_confirmation": "StrongPass123!"
     * }
     *
     * @apiSuccess (202) {String} message Confirmation that the verification email was sent.
     *
     * @apiSuccessExample {json} Accepted
     * {
     *   "message": "A verification code has been sent to your email address."
     * }
     *
     * @apiError (422) ValidationError Invalid or already registered email, or invalid input.
     * @apiError (429) TooManyRequests More than five registration requests in one minute.
     * @apiError (500) MailDeliveryFailed Registration email could not be sent.
     *
     * @apiErrorExample {json} Validation Error
     * {
     *   "message": "The email field is required.",
     *   "errors": {
     *     "email": ["The email field is required."]
     *   }
     * }
     * @apiErrorExample {json} Rate Limit
     * {
     *   "message": "Too Many Attempts."
     * }
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now = now();

        DB::table('registration_otps')->updateOrInsert(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'password' => Hash::make($validated['password']),
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => $now->copy()->addMinutes(10),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        Mail::to($validated['email'])->send(new RegistrationOtpMail($otp));

        return response()->json([
            'message' => 'A verification code has been sent to your email address.',
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * @api {post} /api/register/verify Verify email and complete registration
     * @apiName VerifyRegistration
     * @apiGroup Authentication
     * @apiVersion 1.0.0
     * @apiDescription Verifies the emailed OTP and creates the user account.
     * A maximum of five incorrect codes are allowed, and ten verification
     * requests per minute are allowed.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Content-Type Send the request body as application/json.
     * @apiBody {String} email Registration email address.
     * @apiBody {String} otp Six-digit verification code received by email.
     *
     * @apiExample {curl} Verify registration
     * curl -X POST "https://kovacslaci.hu/zip-api/api/register/verify" \
     *   -H "Accept: application/json" \
     *   -H "Content-Type: application/json" \
     *   -d '{"email":"user@example.com","otp":"012345"}'
     *
     * @apiHeaderExample {json} Request headers
     * {
     *   "Accept": "application/json",
     *   "Content-Type": "application/json"
     * }
     *
     * @apiParamExample {json} Request body
     * {
     *   "email": "user@example.com",
     *   "otp": "012345"
     * }
     *
     * @apiSuccess (201) {Object} user Created and verified user.
     * @apiSuccess (201) {Number} user.id User ID.
     * @apiSuccess (201) {String} user.name User's name.
     * @apiSuccess (201) {String} user.email User's email address.
     * @apiSuccess (201) {String} user.email_verified_at Email verification timestamp.
     * @apiSuccess (201) {String} user.created_at Account creation timestamp.
     * @apiSuccess (201) {String} user.updated_at Account update timestamp.
     * @apiSuccess (201) {String} message Registration completed.
     *
     * @apiSuccessExample {json} Created
     * {
     *   "user": {
     *     "id": 42,
     *     "name": "Test User",
     *     "email": "user@example.com",
     *     "email_verified_at": "2026-11-26T17:00:00.000000Z",
     *     "created_at": "2026-11-26T17:00:00.000000Z",
     *     "updated_at": "2026-11-26T17:00:00.000000Z"
     *   },
     *   "message": "Registration completed successfully."
     * }
     *
     * @apiError (422) ValidationError No pending registration was found, or the code is invalid.
     * @apiError (410) Gone Verification code expired; start registration again.
     * @apiError (429) TooManyRequests Too many incorrect codes or verification requests.
     * @apiError (409) Conflict Email is already registered.
     *
     * @apiErrorExample {json} Invalid Code
     * {
     *   "message": "The verification code is invalid."
     * }
     * @apiErrorExample {json} Expired Code
     * {
     *   "message": "The verification code has expired. Please register again."
     * }
     * @apiErrorExample {json} Too Many Attempts
     * {
     *   "message": "Too many incorrect verification codes. Please request a new code."
     * }
     * @apiErrorExample {json} Email Conflict
     * {
     *   "message": "This email address is already registered."
     * }
     */
    public function verifyRegistration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists('registration_otps', 'email')],
            'otp' => ['required', 'string', 'digits:6'],
        ]);

        return DB::transaction(function () use ($validated): JsonResponse {
            $registration = DB::table('registration_otps')
                ->where('email', $validated['email'])
                ->lockForUpdate()
                ->first();

            if (! $registration) {
                return response()->json([
                    'message' => 'No pending registration was found for this email address.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (Carbon::parse($registration->expires_at)->isPast()) {
                DB::table('registration_otps')->where('email', $validated['email'])->delete();

                return response()->json([
                    'message' => 'The verification code has expired. Please register again.',
                ], Response::HTTP_GONE);
            }

            if ($registration->attempts >= 5) {
                return response()->json([
                    'message' => 'Too many incorrect verification codes. Please request a new code.',
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }

            if (! Hash::check($validated['otp'], $registration->otp_hash)) {
                DB::table('registration_otps')
                    ->where('email', $validated['email'])
                    ->increment('attempts');

                return response()->json([
                    'message' => 'The verification code is invalid.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (User::query()->where('email', $validated['email'])->exists()) {
                DB::table('registration_otps')->where('email', $validated['email'])->delete();

                return response()->json([
                    'message' => 'This email address is already registered.',
                ], Response::HTTP_CONFLICT);
            }

            $user = new User([
                'name' => $registration->name,
                'email' => $registration->email,
                'password' => $registration->password,
            ]);
            $user->email_verified_at = now();
            $user->save();

            DB::table('registration_otps')->where('email', $validated['email'])->delete();

            return response()->json([
                'user' => $user,
                'message' => 'Registration completed successfully.',
            ], Response::HTTP_CREATED);
        });
    }

    /**
     * @api {post} /api/login Log in
     * @apiName Login
     * @apiGroup Authentication
     * @apiVersion 1.0.0
     * @apiDescription Allows five login attempts per minute. A successful
     * login revokes the user's existing Sanctum tokens and returns a new token.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Content-Type Send the request body as application/json.
     * @apiBody {String} email User's email address.
     * @apiBody {String} password User's password.
     *
     * @apiExample {curl} Log in
     * curl -X POST "https://kovacslaci.hu/zip-api/api/login" \
     *   -H "Accept: application/json" \
     *   -H "Content-Type: application/json" \
     *   -d '{"email":"user@example.com","password":"StrongPass123!"}'
     *
     * @apiHeaderExample {json} Request headers
     * {
     *   "Accept": "application/json",
     *   "Content-Type": "application/json"
     * }
     *
     * @apiParamExample {json} Request body
     * {
     *   "email": "user@example.com",
     *   "password": "StrongPass123!"
     * }
     *
     * @apiSuccess (200) {Object} user Authenticated user.
     * @apiSuccess (200) {Number} user.id User ID.
     * @apiSuccess (200) {String} user.name User's name.
     * @apiSuccess (200) {String} user.email User's email address.
     * @apiSuccess (200) {String} user.email_verified_at Email verification timestamp; may be null.
     * @apiSuccess (200) {String} user.created_at Account creation timestamp.
     * @apiSuccess (200) {String} user.updated_at Account update timestamp.
     * @apiSuccess (200) {String} token Sanctum access token.
     *
     * @apiSuccessExample {json} Success
     * {
     *   "user": {
     *     "id": 42,
     *     "name": "Test User",
     *     "email": "user@example.com",
     *     "email_verified_at": "2026-11-26T17:00:00.000000Z",
     *     "created_at": "2026-11-26T17:00:00.000000Z",
     *     "updated_at": "2026-11-26T17:00:00.000000Z"
     *   },
     *   "token": "1|<sanctum-access-token>"
     * }
     *
     * @apiError (422) ValidationError Email or password is missing or invalid.
     * @apiError (401) Unauthorized Invalid email or password.
     * @apiError (429) TooManyRequests More than five login attempts in one minute.
     *
     * @apiErrorExample {json} Validation Error
     * {
     *   "message": "The email field is required.",
     *   "errors": {
     *     "email": ["The email field is required."]
     *   }
     * }
     * @apiErrorExample {json} Invalid Credentials
     * {
     *   "message": "Invalid email or password."
     * }
     * @apiErrorExample {json} Rate Limit
     * {
     *   "message": "Too Many Attempts."
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->tokens()->delete();

        $token = $user->createToken('access')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }
}
