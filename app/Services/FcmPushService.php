<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmPushService
{
    private const TOKEN_CACHE_KEY = 'fcm_access_token';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        if (empty($user->push_token)) {
            return false;
        }

        return $this->send($user->push_token, $title, $body, $data, $user);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(string $deviceToken, string $title, string $body, array $data = [], ?User $user = null): bool
    {
        $projectId = config('services.fcm.project_id');
        $credentialsPath = config('services.fcm.credentials');

        if (! $projectId || ! $credentialsPath || ! is_readable($credentialsPath)) {
            Log::warning('FCM is not configured', [
                'project_id' => (bool) $projectId,
                'credentials_readable' => $credentialsPath ? is_readable($credentialsPath) : false,
            ]);

            return false;
        }

        try {
            $accessToken = $this->getAccessToken($credentialsPath);
            if (! $accessToken) {
                return false;
            }

            $stringData = [];
            foreach ($data as $key => $value) {
                $stringData[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
            }

            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $stringData,
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'default',
                            'sound' => 'default',
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(15)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            if ($response->successful()) {
                return true;
            }

            $errorCode = data_get($response->json(), 'error.status')
                ?? data_get($response->json(), 'error.details.0.errorCode');

            Log::warning('FCM send failed', [
                'user_id' => $user?->id,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            if ($user && $this->isInvalidTokenError($response->status(), $errorCode, $response->body())) {
                $user->update(['push_token' => null]);
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('FCM send exception', [
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function getAccessToken(string $credentialsPath): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 3300, function () use ($credentialsPath) {
            $jsonKey = json_decode((string) file_get_contents($credentialsPath), true);
            if (! is_array($jsonKey)) {
                Log::error('FCM credentials JSON is invalid');

                return null;
            }

            $jwt = $this->createServiceAccountJwt($jsonKey);
            if (! $jwt) {
                return null;
            }

            $response = Http::asForm()
                ->timeout(15)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if (! $response->successful()) {
                Log::error('FCM OAuth token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * @param  array<string, mixed>  $jsonKey
     */
    private function createServiceAccountJwt(array $jsonKey): ?string
    {
        $clientEmail = $jsonKey['client_email'] ?? null;
        $privateKey = $jsonKey['private_key'] ?? null;
        if (! $clientEmail || ! $privateKey) {
            Log::error('FCM service account missing client_email or private_key');

            return null;
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => self::SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $unsigned = $header.'.'.$claim;
        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            Log::error('FCM JWT signing failed');

            return null;
        }

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function isInvalidTokenError(int $status, mixed $errorCode, string $body): bool
    {
        if ($status === 404 || $status === 410) {
            return true;
        }

        $haystack = strtoupper((string) $errorCode.' '.$body);

        return str_contains($haystack, 'UNREGISTERED')
            || str_contains($haystack, 'NOT_FOUND');
    }
}
