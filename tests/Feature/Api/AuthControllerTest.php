<?php

namespace Tests\Feature\Api;

use App\Mail\RegistrationOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass123!';

    public function test_user_is_created_only_after_the_email_otp_is_verified(): void
    {
        $email = 'person@example.com';
        $otp = $this->startRegistration($email);
        $registration = DB::table('registration_otps')->where('email', $email)->first();

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertTrue(Hash::check(self::PASSWORD, $registration->password));
        $this->assertTrue(Hash::check($otp, $registration->otp_hash));

        $this->postJson('/api/register/verify', [
            'email' => $email,
            'otp' => $otp,
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', $email)
            ->assertJsonPath('user.name', 'Test User');

        $user = User::query()->where('email', $email)->firstOrFail();

        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertDatabaseMissing('registration_otps', ['email' => $email]);
    }

    public function test_invalid_otp_does_not_create_user_and_increments_attempts(): void
    {
        $email = 'person@example.com';
        $otp = $this->startRegistration($email);
        $invalidOtp = $otp === '000000' ? '000001' : '000000';

        $this->postJson('/api/register/verify', [
            'email' => $email,
            'otp' => $invalidOtp,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The verification code is invalid.');

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertDatabaseHas('registration_otps', [
            'email' => $email,
            'attempts' => 1,
        ]);
    }

    public function test_expired_otp_cannot_complete_registration(): void
    {
        $email = 'person@example.com';
        $otp = $this->startRegistration($email);
        DB::table('registration_otps')
            ->where('email', $email)
            ->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/register/verify', [
            'email' => $email,
            'otp' => $otp,
        ])
            ->assertGone();

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertDatabaseMissing('registration_otps', ['email' => $email]);
    }

    public function test_verification_is_blocked_after_five_incorrect_attempts(): void
    {
        $email = 'person@example.com';
        $otp = $this->startRegistration($email);
        DB::table('registration_otps')
            ->where('email', $email)
            ->update(['attempts' => 5]);

        $this->postJson('/api/register/verify', [
            'email' => $email,
            'otp' => $otp,
        ])
            ->assertTooManyRequests();

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_registration_requires_validated_details_before_sending_email(): void
    {
        Mail::fake();

        $this->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('registration_otps', 0);
    }

    public function test_login_returns_a_sanctum_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'person@example.com',
            'password' => self::PASSWORD,
        ]);

        $this->postJson('/api/login', [
            'email' => 'person@example.com',
            'password' => self::PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'access',
        ]);
    }

    private function startRegistration(string $email): string
    {
        Mail::fake();

        $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => $email,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertAccepted();

        $registration = DB::table('registration_otps')->where('email', $email)->first();
        $otp = '';

        Mail::assertSent(RegistrationOtpMail::class, function (RegistrationOtpMail $mail) use ($email, $registration, &$otp): bool {
            $otp = $mail->otp;

            return $mail->hasTo($email)
                && Hash::check($mail->otp, $registration->otp_hash);
        });

        return $otp;
    }
}
