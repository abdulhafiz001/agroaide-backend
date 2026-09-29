<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Mail\PasswordResetCodeMail;
use App\Mail\WelcomeMail;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token_and_profile(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullName' => 'Test Farmer',
            'email' => 'farmer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'crops' => ['Maize', 'Cassava'],
            'farmLatitude' => 9.05,
            'farmLongitude' => 7.49,
            'termsVersion' => config('legal.terms.version'),
            'privacyVersion' => config('legal.privacy.version'),
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'profile' => ['email', 'fullName']]);

        $this->assertDatabaseHas('users', ['email' => 'farmer@example.com']);
    }

    public function test_register_rejects_a_single_word_name(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullName' => 'John',
            'email' => 'singleword@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'termsVersion' => config('legal.terms.version'),
            'privacyVersion' => config('legal.privacy.version'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['fullName']);
        $this->assertDatabaseMissing('users', ['email' => 'singleword@example.com']);
    }

    public function test_register_rejects_a_name_with_numbers(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullName' => 'Farmer 99',
            'email' => 'numbers@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'termsVersion' => config('legal.terms.version'),
            'privacyVersion' => config('legal.privacy.version'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['fullName']);
    }

    public function test_login_with_email_returns_token(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
            'name' => 'Login Farmer',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'profile']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_profile_when_authenticated(): void
    {
        $user = User::factory()->create([
            'email' => 'me@example.com',
            'name' => 'Me Farmer',
        ]);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('profile.email', 'me@example.com');
    }

    public function test_registration_creates_verification_otp_and_sends_verification_mail(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'fullName' => 'Bala Danjuma',
            'email' => 'bala@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'termsVersion' => config('legal.terms.version'),
            'privacyVersion' => config('legal.privacy.version'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('profile.emailVerified', false);

        $user = User::where('email', 'bala@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('email_verification_otps', ['user_id' => $user->id]);

        Mail::assertSent(WelcomeMail::class);
        Mail::assertSent(EmailVerificationCodeMail::class);
    }

    public function test_send_email_verification_code_dispatches_otp(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'farmer.otp@example.com',
            'name' => 'Bosede Alabi',
            'email_verified_at' => null,
        ]);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/email/send-code');

        $response->assertOk()
            ->assertJsonPath('message', 'A 6-digit verification code has been sent to your email address.');

        $this->assertDatabaseHas('email_verification_otps', ['user_id' => $user->id]);
        Mail::assertSent(EmailVerificationCodeMail::class);
    }

    public function test_verify_email_with_valid_code_marks_email_verified(): void
    {
        $user = User::factory()->create([
            'email' => 'chidi.verify@example.com',
            'name' => 'Chidi Eze',
            'email_verified_at' => null,
        ]);
        $token = $user->createToken('mobile-app')->plainTextToken;

        EmailVerificationOtp::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(15),
            'attempts' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/email/verify', [
                'code' => '654321',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Email verified successfully.')
            ->assertJsonPath('profile.emailVerified', true);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $user->id]);
    }

    public function test_verify_email_rejects_incorrect_code(): void
    {
        $user = User::factory()->create([
            'email' => 'wrong.code@example.com',
            'name' => 'Yakubu Gowon',
            'email_verified_at' => null,
        ]);
        $token = $user->createToken('mobile-app')->plainTextToken;

        EmailVerificationOtp::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('111222'),
            'expires_at' => now()->addMinutes(15),
            'attempts' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/email/verify', [
                'code' => '999999',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_updating_email_resets_verification_status(): void
    {
        $user = User::factory()->create([
            'email' => 'old.email@example.com',
            'name' => 'Zainab Bello',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/auth/profile', [
                'email' => 'new.email@example.com',
            ]);

        $response->assertOk()
            ->assertJsonPath('profile.email', 'new.email@example.com')
            ->assertJsonPath('profile.emailVerified', false);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_email_templates_render_cleanly(): void
    {
        $user = User::factory()->make([
            'name' => 'Aminu Kano',
            'email' => 'aminu@example.com',
        ]);

        $welcome = new WelcomeMail($user);
        $welcomeHtml = $welcome->render();
        $this->assertStringContainsString('Welcome to AgroAide', $welcomeHtml);
        $this->assertStringContainsString('Aminu Kano', $welcomeHtml);
        $this->assertStringContainsString('AgroAide Farm Intelligence', $welcomeHtml);

        $verify = new EmailVerificationCodeMail($user, '123456', 15);
        $verifyHtml = $verify->render();
        $this->assertStringContainsString('123456', $verifyHtml);
        $this->assertStringContainsString('Verify Your Email Address', $verifyHtml);

        $reset = new PasswordResetCodeMail($user, '654321', 15);
        $resetHtml = $reset->render();
        $this->assertStringContainsString('654321', $resetHtml);
        $this->assertStringContainsString('Password Recovery Code', $resetHtml);
    }
}
