<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Mail\WelcomeMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AiAdvisorService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const OTP_TTL_MINUTES = 15;

    private const OTP_MAX_ATTEMPTS = 5;

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'phoneNumber' => ['nullable', 'string', 'max:32'],
            'farmName' => ['nullable', 'string', 'max:255'],
            'farmLocation' => ['nullable', 'string', 'max:255'],
            'farmSizeM2' => ['nullable', 'numeric', 'min:0'],
            'crops' => ['nullable', 'array'],
            'crops.*' => ['string', 'max:100'],
            'experienceLevel' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'soilType' => ['nullable', 'string', 'max:100'],
            'irrigationAccess' => ['nullable', Rule::in(['rain-fed', 'drip', 'sprinkler', 'flood'])],
            'farmLatitude' => ['nullable', 'numeric', 'between:-90,90'],
            'farmLongitude' => ['nullable', 'numeric', 'between:-180,180'],
            'preferredLanguage' => ['nullable', 'string', Rule::in(['en', 'ha', 'yo', 'pcm'])],
            'termsVersion' => ['required', 'in:'.config('legal.terms.version')],
            'privacyVersion' => ['required', 'in:'.config('legal.privacy.version')],
            'researchConsent' => ['sometimes', 'boolean'],
        ]);
        $normalizedPhone = filled($validated['phoneNumber'] ?? null) ? PhoneNumber::normalize($validated['phoneNumber']) : null;
        if ($normalizedPhone && User::where('phone_normalized', $normalizedPhone)->exists()) {
            throw ValidationException::withMessages(['phoneNumber' => ['The phone number has already been taken.']]);
        }

        $user = User::create([
            'name' => $validated['fullName'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'farmer',
            'phone_number' => $validated['phoneNumber'] ?? null,
            'phone_normalized' => $normalizedPhone,
            'farm_name' => $validated['farmName'] ?? null,
            'farm_location' => $validated['farmLocation'] ?? null,
            'farm_latitude' => $validated['farmLatitude'] ?? null,
            'farm_longitude' => $validated['farmLongitude'] ?? null,
            'farm_size_m2' => $validated['farmSizeM2'] ?? 0,
            'crops' => $validated['crops'] ?? [],
            'experience_level' => $validated['experienceLevel'] ?? 'beginner',
            'soil_type' => $validated['soilType'] ?? 'Loamy',
            'irrigation_access' => $validated['irrigationAccess'] ?? 'drip',
            'preferred_language' => $validated['preferredLanguage'] ?? 'en',
        ]);

        UserConsent::create([
            'user_id' => $user->id,
            'terms_version' => $validated['termsVersion'],
            'privacy_version' => $validated['privacyVersion'],
            'research_version' => config('legal.research_consent.version'),
            'research_consent' => $validated['researchConsent'] ?? false,
            'consented_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        $user->tokens()->delete();
        $token = $user->createToken('mobile-app')->plainTextToken;

        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Throwable) {
            // Registration should still succeed if mail delivery fails.
        }

        return response()->json([
            'token' => $token,
            'profile' => $this->transformUserProfile($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required_without:email', 'nullable', 'string', 'max:255'],
            'email' => ['required_without:identifier', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim((string) ($credentials['identifier'] ?? $credentials['email'] ?? ''));
        $user = $this->findUserByIdentifier($identifier);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'profile' => $this->transformUserProfile($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'profile' => $this->transformUserProfile($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Signed out successfully.',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'fullName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phoneNumber' => ['nullable', 'string', 'max:32'],
            'farmName' => ['nullable', 'string', 'max:255'],
            'farmLocation' => ['nullable', 'string', 'max:255'],
            'farmLatitude' => ['nullable', 'numeric', 'between:-90,90'],
            'farmLongitude' => ['nullable', 'numeric', 'between:-180,180'],
            'farmSizeM2' => ['nullable', 'numeric', 'min:0'],
            'preferredLanguage' => ['nullable', 'string', Rule::in(['en', 'ha', 'yo', 'pcm'])],
            'pushToken' => ['nullable', 'string', 'max:4096'],
            'notificationPreferences' => ['nullable', 'array'],
            'notificationPreferences.severeWeather' => ['nullable', 'boolean'],
            'notificationPreferences.aiInsights' => ['nullable', 'boolean'],
            'notificationPreferences.plantingWindowAlerts' => ['nullable', 'boolean'],
            'notificationPreferences.fieldBoundaryReminders' => ['nullable', 'boolean'],
            'notificationPreferences.diseaseOutbreak' => ['nullable', 'boolean'],
            'crops' => ['nullable', 'array'],
            'crops.*' => ['string', 'max:100'],
            'experienceLevel' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'soilType' => ['nullable', 'string', 'max:100'],
            'irrigationAccess' => ['nullable', Rule::in(['rain-fed', 'drip', 'sprinkler', 'flood'])],
            'aiResponseDepth' => ['nullable', Rule::in(['concise', 'balanced', 'deep'])],
            'aiRiskTolerance' => ['nullable', Rule::in(['cautious', 'balanced', 'bold'])],
        ]);

        $updateData = [];
        if (isset($validated['fullName'])) {
            $updateData['name'] = $validated['fullName'];
        }
        if (isset($validated['email'])) {
            $updateData['email'] = $validated['email'];
        }
        if (array_key_exists('phoneNumber', $validated)) {
            $normalizedPhone = filled($validated['phoneNumber']) ? PhoneNumber::normalize($validated['phoneNumber']) : null;
            if ($normalizedPhone && User::where('phone_normalized', $normalizedPhone)->whereKeyNot($user->id)->exists()) {
                throw ValidationException::withMessages(['phoneNumber' => ['The phone number has already been taken.']]);
            }
            $updateData['phone_number'] = $validated['phoneNumber'];
            $updateData['phone_normalized'] = $normalizedPhone;
        }
        if (array_key_exists('farmName', $validated)) {
            $updateData['farm_name'] = $validated['farmName'];
        }
        if (array_key_exists('farmLocation', $validated)) {
            $updateData['farm_location'] = $validated['farmLocation'];
        }
        if (array_key_exists('farmLatitude', $validated)) {
            $updateData['farm_latitude'] = $validated['farmLatitude'];
        }
        if (array_key_exists('farmLongitude', $validated)) {
            $updateData['farm_longitude'] = $validated['farmLongitude'];
        }
        if (isset($validated['farmSizeM2'])) {
            $updateData['farm_size_m2'] = $validated['farmSizeM2'];
        }
        if (isset($validated['crops'])) {
            $updateData['crops'] = $validated['crops'];
        }
        if (isset($validated['experienceLevel'])) {
            $updateData['experience_level'] = $validated['experienceLevel'];
        }
        if (isset($validated['soilType'])) {
            $updateData['soil_type'] = $validated['soilType'];
        }
        if (isset($validated['irrigationAccess'])) {
            $updateData['irrigation_access'] = $validated['irrigationAccess'];
        }
        if (isset($validated['preferredLanguage'])) {
            $updateData['preferred_language'] = $validated['preferredLanguage'];
            AiAdvisorService::forgetDailyInsightCache((int) $user->id);
        }
        if (isset($validated['aiResponseDepth'])) {
            $updateData['ai_response_depth'] = $validated['aiResponseDepth'];
        }
        if (isset($validated['aiRiskTolerance'])) {
            $updateData['ai_risk_tolerance'] = $validated['aiRiskTolerance'];
        }
        if (array_key_exists('pushToken', $validated)) {
            $updateData['push_token'] = $validated['pushToken'];
        }
        if (isset($validated['notificationPreferences'])) {
            $current = is_array($user->notification_preferences) ? $user->notification_preferences : [];
            $incoming = $validated['notificationPreferences'];
            // diseaseOutbreak is always on — ignore client attempts to disable it
            unset($incoming['diseaseOutbreak']);
            $merged = array_merge($current, $incoming);
            $merged['diseaseOutbreak'] = true;
            $updateData['notification_preferences'] = $merged;
        }

        $user->update($updateData);
        $user->refresh();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'profile' => $this->transformUserProfile($user),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($validated['currentPassword'], $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($validated['newPassword'])]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required_without:email', 'nullable', 'string', 'max:255'],
            'email' => ['required_without:identifier', 'nullable', 'string', 'max:255'],
        ]);

        $identifier = trim((string) ($validated['identifier'] ?? $validated['email'] ?? ''));
        $ip = (string) $request->ip();

        $rateKey = 'password-reset:'.sha1(strtolower($identifier).'|'.$ip);
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return response()->json([
                'message' => "Too many recovery attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }
        RateLimiter::hit($rateKey, 3600);

        $genericMessage = 'If an account exists for that email or phone, a recovery code has been sent to the registered email address.';

        $user = $this->findUserByIdentifier($identifier);

        if ($user && filled($user->email)) {
            $userRateKey = 'password-reset-user:'.$user->id;
            if (! RateLimiter::tooManyAttempts($userRateKey, 3)) {
                RateLimiter::hit($userRateKey, 3600);

                $code = (string) random_int(100000, 999999);

                PasswordResetOtp::where('user_id', $user->id)->delete();
                PasswordResetOtp::create([
                    'user_id' => $user->id,
                    'code_hash' => Hash::make($code),
                    'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
                    'attempts' => 0,
                    'request_ip' => $ip,
                ]);

                try {
                    Mail::to($user->email)->send(new PasswordResetCodeMail($user, $code, self::OTP_TTL_MINUTES));
                } catch (\Throwable) {
                    // Keep generic response to avoid leaking mail infrastructure issues.
                }
            }
        }

        return response()->json([
            'message' => $genericMessage,
        ]);
    }

    public function resetPasswordWithCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->findUserByIdentifier(trim($validated['identifier']));

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired recovery code.'],
            ]);
        }

        /** @var PasswordResetOtp|null $otp */
        $otp = PasswordResetOtp::where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (! $otp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired recovery code.'],
            ]);
        }

        if ($otp->attempts >= self::OTP_MAX_ATTEMPTS) {
            $otp->delete();
            throw ValidationException::withMessages([
                'code' => ['Too many invalid attempts. Please request a new recovery code.'],
            ]);
        }

        if (! Hash::check($validated['code'], $otp->code_hash)) {
            $otp->increment('attempts');
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired recovery code.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);
        $user->tokens()->delete();
        $otp->delete();

        return response()->json([
            'message' => 'Password updated successfully. You can sign in with your new password.',
        ]);
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        if ($identifier === '') {
            return null;
        }

        if (str_contains($identifier, '@')) {
            return User::whereRaw('LOWER(email) = ?', [strtolower($identifier)])->first();
        }

        $normalized = PhoneNumber::normalize($identifier);
        if ($normalized === '') {
            return null;
        }

        return User::where('phone_normalized', $normalized)->first();
    }

    private function transformUserProfile(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'fullName' => $user->name,
            'email' => $user->email,
            'phoneNumber' => $user->phone_number ?? '',
            'farmName' => $user->farm_name ?? 'My Farm',
            'farmLocation' => $user->farm_location ?? 'Unknown location',
            'farmSizeM2' => (float) ($user->farm_size_m2 ?? 0),
            'crops' => is_array($user->crops) ? $user->crops : [],
            'experienceLevel' => $user->experience_level ?? 'beginner',
            'soilType' => $user->soil_type ?? 'Loamy',
            'irrigationAccess' => $user->irrigation_access ?? 'drip',
            'avatarColor' => $user->avatar_color ?? '#57b346',
            'preferredTheme' => $user->preferred_theme ?? 'light',
            'farmLatitude' => $user->farm_latitude,
            'farmLongitude' => $user->farm_longitude,
            'preferredLanguage' => $user->preferred_language ?? 'en',
            'notificationPreferences' => $this->resolveNotificationPreferences($user),
            'aiResponseDepth' => $user->ai_response_depth ?? 'balanced',
            'aiRiskTolerance' => $user->ai_risk_tolerance ?? 'balanced',
            'consentRequired' => ! $user->consents()
                ->where('terms_version', config('legal.terms.version'))
                ->where('privacy_version', config('legal.privacy.version'))
                ->exists(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function resolveNotificationPreferences(User $user): array
    {
        $defaults = [
            'severeWeather' => true,
            'aiInsights' => true,
            'plantingWindowAlerts' => true,
            'fieldBoundaryReminders' => true,
            'diseaseOutbreak' => true,
        ];

        $prefs = is_array($user->notification_preferences) ? $user->notification_preferences : [];
        $resolved = array_merge($defaults, $prefs);
        $resolved['diseaseOutbreak'] = true;

        return [
            'severeWeather' => (bool) $resolved['severeWeather'],
            'aiInsights' => (bool) $resolved['aiInsights'],
            'plantingWindowAlerts' => (bool) $resolved['plantingWindowAlerts'],
            'fieldBoundaryReminders' => (bool) $resolved['fieldBoundaryReminders'],
            'diseaseOutbreak' => true,
        ];
    }
}
