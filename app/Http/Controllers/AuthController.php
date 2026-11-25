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
     *
     * @apiName Register
     *
     * @apiGroup Authentication
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Starts registration by emailing a six-digit OTP. The user
     * account is created only after the code is verified. The OTP expires in
     * 10 minutes.
     *
     * @apiBody {String} name User's name.
     * @apiBody {String} email User's email address.
     * @apiBody {String} password Password (minimum 8 characters).
     * @apiBody {String} password_confirmation Password confirmation.
     *
     * @apiSuccess (202) {String} message Verification email sent.
     *
     * @apiError ValidationError Invalid or already registered email, or invalid input.
     * @apiError TooManyRequests Registration requests are rate limited.
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
     *
     * @apiName VerifyRegistration
     *
     * @apiGroup Authentication
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Verifies the emailed OTP and creates the user account.
     * A maximum of five incorrect codes are allowed.
     *
     * @apiBody {String} email Registration email address.
     * @apiBody {String} otp Six-digit verification code.
     *
     * @apiSuccess (201) {Object} user Created and verified user.
     * @apiSuccess (201) {Number} user.id User ID.
     * @apiSuccess (201) {String} user.name User's name.
     * @apiSuccess (201) {String} user.email User's email address.
     * @apiSuccess (201) {String} message Registration completed.
     *
     * @apiError ValidationError Missing registration or invalid code.
     * @apiError Gone Verification code expired.
     * @apiError TooManyRequests Too many incorrect codes.
     * @apiError Conflict Email is already registered.
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
     *
     * @apiName Login
     *
     * @apiGroup Authentication
     *
     * @apiVersion 1.0.0
     *
     * @apiBody {String} email User's email address.
     * @apiBody {String} password User's password.
     *
     * @apiSuccess {Object} user Authenticated user.
     * @apiSuccess {String} token Sanctum access token.
     *
     * @apiError Unauthorized Invalid email or password.
     * @apiError TooManyRequests Login attempts are rate limited.
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
