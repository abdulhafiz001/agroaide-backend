<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Mail\WelcomeMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
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
            'password' => ['required', 'string', 'confirmed', 'min:8'],
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
        ]);

        $user = User::create([
            'name' => $validated['fullName'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone_number' => $validated['phoneNumber'] ?? null,
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
        /** @var \App\Models\User $user */
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
        /** @var \App\Models\User $user */
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
            'notificationPreferences.marketMovers' => ['nullable', 'boolean'],
            'notificationPreferences.aiInsights' => ['nullable', 'boolean'],
            'notificationPreferences.communityMentions' => ['nullable', 'boolean'],
            'crops' => ['nullable', 'array'],
            'crops.*' => ['string', 'max:100'],
            'experienceLevel' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'soilType' => ['nullable', 'string', 'max:100'],
            'irrigationAccess' => ['nullable', Rule::in(['rain-fed', 'drip', 'sprinkler', 'flood'])],
        ]);

        $updateData = [];
        if (isset($validated['fullName'])) {
            $updateData['name'] = $validated['fullName'];
        }
        if (isset($validated['email'])) {
            $updateData['email'] = $validated['email'];
        }
        if (array_key_exists('phoneNumber', $validated)) {
            $updateData['phone_number'] = $validated['phoneNumber'];
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
        }
        if (array_key_exists('pushToken', $validated)) {
            $updateData['push_token'] = $validated['pushToken'];
        }
        if (isset($validated['notificationPreferences'])) {
            $current = is_array($user->notification_preferences) ? $user->notification_preferences : [];
            $updateData['notification_preferences'] = array_merge($current, $validated['notificationPreferences']);
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

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! Hash::check($validated['currentPassword'], $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($validated['newPassword'])]);

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

        /** @var \App\Models\PasswordResetOtp|null $otp */
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

        return User::query()
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->get()
            ->first(fn (User $user) => PhoneNumber::matches($user->phone_number, $identifier));
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
            'notificationPreferences' => $user->notification_preferences ?? [
                'severeWeather' => true,
                'marketMovers' => true,
                'aiInsights' => true,
                'communityMentions' => false,
            ],
        ];
    }
}
