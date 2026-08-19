<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationDispatcher
{
    public function __construct(
        private FcmPushService $fcm,
        private LlmResponseCleaner $cleaner,
    ) {}

    /**
     * Create an in-app notification and optionally send FCM push.
     *
     * @param  array<string, mixed>  $data
     * @param  array{
     *     push?: bool,
     *     preference?: string|null,
     *     dedupeMinutes?: int|null,
     *     dedupeKey?: string|null,
     * }  $options
     */
    public function notify(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = [],
        array $options = [],
    ): ?AppNotification {
        $sendPush = $options['push'] ?? true;
        $preference = $options['preference'] ?? null;
        $dedupeMinutes = $options['dedupeMinutes'] ?? null;
        $dedupeKey = $options['dedupeKey'] ?? null;

        if ($preference && ! $this->prefEnabled($user, $preference)) {
            return null;
        }

        if ($dedupeMinutes && $this->isDuplicate($user, $type, $dedupeMinutes, $dedupeKey, $data)) {
            return null;
        }

        $message = $this->cleaner->farmerFacing($message, $this->fallbackMessage($type, $data, $title));

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => array_merge($data, ['type' => $type]),
        ]);

        if ($sendPush) {
            $pushed = $this->fcm->sendToUser(
                $user,
                $title,
                $this->pushBody($message),
                array_merge($data, ['type' => $type, 'notificationId' => (string) $notification->id]),
            );
            if (! $pushed && ! empty($user->push_token)) {
                // In-app row still exists; FcmPushService already logged the provider error.
                logger()->notice('In-app notification saved but FCM push did not succeed', [
                    'user_id' => $user->id,
                    'type' => $type,
                    'notification_id' => $notification->id,
                ]);
            }
        }

        return $notification;
    }

    public function prefEnabled(User $user, string $key): bool
    {
        $defaults = [
            'severeWeather' => true,
            'aiInsights' => true,
            'plantingWindowAlerts' => true,
            'fieldBoundaryReminders' => true,
            'diseaseOutbreak' => true,
        ];

        if ($key === 'diseaseOutbreak') {
            return true;
        }

        $prefs = $user->notification_preferences;
        if (! is_array($prefs)) {
            return $defaults[$key] ?? true;
        }

        if (! array_key_exists($key, $prefs)) {
            return $defaults[$key] ?? true;
        }

        return (bool) $prefs[$key];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isDuplicate(
        User $user,
        string $type,
        int $dedupeMinutes,
        ?string $dedupeKey,
        array $data,
    ): bool {
        $query = AppNotification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subMinutes($dedupeMinutes));

        if ($dedupeKey && array_key_exists($dedupeKey, $data)) {
            $query->whereJsonContains("data->{$dedupeKey}", $data[$dedupeKey]);
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fallbackMessage(string $type, array $data, string $title): string
    {
        $crop = trim((string) ($data['crop'] ?? ''));
        $place = trim((string) ($data['location'] ?? $data['farmLocation'] ?? ''));
        $date = trim((string) ($data['bestPlantDate'] ?? $data['plantOn'] ?? ''));

        return match ($type) {
            'crop_watch_planting', 'planting_window' => trim(
                ($crop !== '' ? "Good time to plant {$crop}" : $title)
                .($place !== '' ? " around {$place}" : '')
                .($date !== '' ? ". Best planting date: {$date}." : '.')
            ),
            'crop_watch_season_passed' => $crop !== ''
                ? "The planting season for {$crop} has already passed for this year."
                : $title,
            'crop_watch_invalid' => $crop !== ''
                ? "\"{$crop}\" does not look like a valid farm crop."
                : $title,
            default => $title !== '' ? $title : 'Open AgroAide for details.',
        };
    }

    private function pushBody(string $message): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', $message) ?? $message);
        if (strlen($plain) <= 180) {
            return $plain;
        }

        return rtrim(substr($plain, 0, 177)).'…';
    }
}
