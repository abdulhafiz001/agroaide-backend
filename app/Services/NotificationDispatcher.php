<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationDispatcher
{
    public function __construct(private FcmPushService $fcm) {}

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

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => array_merge($data, ['type' => $type]),
        ]);

        if ($sendPush) {
            $this->fcm->sendToUser(
                $user,
                $title,
                $message,
                array_merge($data, ['type' => $type, 'notificationId' => (string) $notification->id]),
            );
        }

        return $notification;
    }

    public function prefEnabled(User $user, string $key): bool
    {
        $defaults = [
            'severeWeather' => true,
            'marketMovers' => true,
            'aiInsights' => true,
            'communityMentions' => false,
        ];

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
}
