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
        $serviceAccount = $this->resolveServiceAccount();

        if (! $projectId || ! $serviceAccount) {
            $credentialsPath = config('services.fcm.credentials');
            Log::warning('FCM is not configured', [
                'project_id' => (bool) $projectId,
                'credentials_path' => $credentialsPath,
                'credentials_exists' => is_string($credentialsPath) && file_exists($credentialsPath),
                'credentials_readable' => is_string($credentialsPath) && is_readable($credentialsPath),
                'credentials_json_set' => filled(config('services.fcm.credentials_json')),
                'credentials_base64_set' => filled(config('services.fcm.credentials_base64')),
            ]);

            return false;
        }

        try {
            $accessToken = $this->getAccessToken($serviceAccount);
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
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1,
                            ],
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

    /**
     * Whether FCM can authenticate (project id + service account available).
     */
    public function isConfigured(): bool
    {
        return filled(config('services.fcm.project_id')) && $this->resolveServiceAccount() !== null;
    }

    /**
     * Human-readable status for artisan diagnostics (no secrets leaked).
     *
     * @return array{
     *     configured: bool,
     *     projectIdSet: bool,
     *     source: string,
     *     path: string|null,
     *     pathExists: bool,
     *     pathReadable: bool,
     *     jsonEnvSet: bool,
     *     base64EnvSet: bool,
     *     hint: string|null
     * }
     */
    public function configurationStatus(): array
    {
        $path = config('services.fcm.credentials');
        $pathExists = is_string($path) && file_exists($path);
        $pathReadable = is_string($path) && is_readable($path);
        $jsonSet = filled(config('services.fcm.credentials_json'));
        $base64Set = filled(config('services.fcm.credentials_base64'));
        $account = $this->resolveServiceAccount();

        $source = 'none';
        if ($account) {
            if ($base64Set) {
                $source = 'FCM_CREDENTIALS_BASE64';
            } elseif ($jsonSet) {
                $source = 'FCM_CREDENTIALS_JSON';
            } elseif ($pathReadable) {
                $source = 'FCM_CREDENTIALS_PATH file';
            } else {
                $source = 'resolved';
            }
        }

        $hint = null;
        if (! $account) {
            if (! $pathExists && ! $jsonSet && ! $base64Set) {
                $hint = 'The service-account JSON is not in the container. On Coolify, set FCM_CREDENTIALS_BASE64 (recommended) or mount the file and set FCM_CREDENTIALS_PATH.';
            } elseif ($pathExists && ! $pathReadable) {
                $hint = "File exists at {$path} but is not readable by PHP. Fix ownership/permissions (e.g. chmod 640, chown www-data).";
            } elseif ($jsonSet || $base64Set) {
                $hint = 'FCM credentials env is set but JSON could not be decoded. Check it is valid service-account JSON (or valid base64 of that JSON).';
            } else {
                $hint = "Path {$path} is not readable. Upload/mount firebase-service-account.json or use FCM_CREDENTIALS_BASE64.";
            }
        }

        return [
            'configured' => filled(config('services.fcm.project_id')) && $account !== null,
            'projectIdSet' => filled(config('services.fcm.project_id')),
            'source' => $source,
            'path' => is_string($path) ? $path : null,
            'pathExists' => $pathExists,
            'pathReadable' => $pathReadable,
            'jsonEnvSet' => $jsonSet,
            'base64EnvSet' => $base64Set,
            'hint' => $hint,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveServiceAccount(): ?array
    {
        $base64 = config('services.fcm.credentials_base64');
        if (is_string($base64) && trim($base64) !== '') {
            $decoded = base64_decode(trim($base64), true);
            if ($decoded !== false) {
                $json = json_decode($decoded, true);
                if (is_array($json) && ! empty($json['private_key']) && ! empty($json['client_email'])) {
                    return $json;
                }
            }
            Log::warning('FCM_CREDENTIALS_BASE64 is set but could not be decoded into a valid service account JSON');
        }

        $rawJson = config('services.fcm.credentials_json');
        if (is_string($rawJson) && trim($rawJson) !== '') {
            $json = json_decode($rawJson, true);
            if (is_array($json) && ! empty($json['private_key']) && ! empty($json['client_email'])) {
                return $json;
            }
            Log::warning('FCM_CREDENTIALS_JSON is set but is not valid service account JSON');
        }

        $credentialsPath = config('services.fcm.credentials');
        if (is_string($credentialsPath) && is_readable($credentialsPath)) {
            $json = json_decode((string) file_get_contents($credentialsPath), true);
            if (is_array($json) && ! empty($json['private_key']) && ! empty($json['client_email'])) {
                return $json;
            }
            Log::warning('FCM credentials file is readable but JSON is invalid', ['path' => $credentialsPath]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $jsonKey
     */
    private function getAccessToken(array $jsonKey): ?string
    {
        $cacheKey = self::TOKEN_CACHE_KEY.':'.sha1((string) ($jsonKey['client_email'] ?? 'unknown'));

        return Cache::remember($cacheKey, 3300, function () use ($jsonKey) {
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

        // Coolify / env paste sometimes turns literal \n into the two characters \ and n.
        if (is_string($privateKey) && str_contains($privateKey, '\\n')) {
            $privateKey = str_replace('\\n', "\n", $privateKey);
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
