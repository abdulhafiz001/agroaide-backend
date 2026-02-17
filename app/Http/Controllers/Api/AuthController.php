<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
            'phoneNumber' => ['nullable', 'string', 'max:32'],
            'farmName' => ['nullable', 'string', 'max:255'],
            'farmLocation' => ['nullable', 'string', 'max:255'],
            'farmSizeHectares' => ['nullable', 'numeric', 'min:0'],
            'crops' => ['nullable', 'array'],
            'crops.*' => ['string', 'max:100'],
            'experienceLevel' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'soilType' => ['nullable', 'string', 'max:100'],
            'irrigationAccess' => ['nullable', Rule::in(['rain-fed', 'drip', 'sprinkler', 'flood'])],
            'farmLatitude' => ['nullable', 'numeric', 'between:-90,90'],
            'farmLongitude' => ['nullable', 'numeric', 'between:-180,180'],
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
            'farm_size_hectares' => $validated['farmSizeHectares'] ?? 0,
            'crops' => $validated['crops'] ?? [],
            'experience_level' => $validated['experienceLevel'] ?? 'beginner',
            'soil_type' => $validated['soilType'] ?? 'Loamy',
            'irrigation_access' => $validated['irrigationAccess'] ?? 'drip',
        ]);

        $user->tokens()->delete();
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'profile' => $this->transformUserProfile($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
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
            'farmSizeHectares' => ['nullable', 'numeric', 'min:0'],
            'crops' => ['nullable', 'array'],
            'crops.*' => ['string', 'max:100'],
            'experienceLevel' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'soilType' => ['nullable', 'string', 'max:100'],
            'irrigationAccess' => ['nullable', Rule::in(['rain-fed', 'drip', 'sprinkler', 'flood'])],
        ]);

        $updateData = [];
        if (isset($validated['fullName'])) $updateData['name'] = $validated['fullName'];
        if (isset($validated['email'])) $updateData['email'] = $validated['email'];
        if (array_key_exists('phoneNumber', $validated)) $updateData['phone_number'] = $validated['phoneNumber'];
        if (array_key_exists('farmName', $validated)) $updateData['farm_name'] = $validated['farmName'];
        if (array_key_exists('farmLocation', $validated)) $updateData['farm_location'] = $validated['farmLocation'];
        if (array_key_exists('farmLatitude', $validated)) $updateData['farm_latitude'] = $validated['farmLatitude'];
        if (array_key_exists('farmLongitude', $validated)) $updateData['farm_longitude'] = $validated['farmLongitude'];
        if (isset($validated['farmSizeHectares'])) $updateData['farm_size_hectares'] = $validated['farmSizeHectares'];
        if (isset($validated['crops'])) $updateData['crops'] = $validated['crops'];
        if (isset($validated['experienceLevel'])) $updateData['experience_level'] = $validated['experienceLevel'];
        if (isset($validated['soilType'])) $updateData['soil_type'] = $validated['soilType'];
        if (isset($validated['irrigationAccess'])) $updateData['irrigation_access'] = $validated['irrigationAccess'];

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
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Unable to send recovery link. Please verify this email.'],
            ]);
        }

        $token = Password::broker()->createToken($user);

        return response()->json([
            'message' => 'Recovery link sent successfully.',
            'resetToken' => $token,
        ]);
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
            'farmSizeHectares' => (float) ($user->farm_size_hectares ?? 0),
            'crops' => is_array($user->crops) ? $user->crops : [],
            'experienceLevel' => $user->experience_level ?? 'beginner',
            'soilType' => $user->soil_type ?? 'Loamy',
            'irrigationAccess' => $user->irrigation_access ?? 'drip',
            'avatarColor' => $user->avatar_color ?? '#57b346',
            'preferredTheme' => $user->preferred_theme ?? 'light',
            'farmLatitude' => $user->farm_latitude,
            'farmLongitude' => $user->farm_longitude,
        ];
    }
}
