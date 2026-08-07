<?php

namespace App\Services;

use App\Models\AdvisorConversation;
use App\Models\FarmImageAnalysis;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

class DailyUsageLimitService
{
    public function assertCanScan(User $user): void
    {
        $limit = (int) config('security.daily_limits.scans', 4);
        $used = $this->scansUsedToday($user);
        if ($used >= $limit) {
            $this->reject('daily_scan_limit', 'Daily scan limit reached. Come back tomorrow to scan again.', $limit, $used);
        }
    }

    public function assertCanChat(User $user): void
    {
        $limit = (int) config('security.daily_limits.chat_messages', 8);
        $used = $this->chatMessagesUsedToday($user);
        if ($used >= $limit) {
            $this->reject('daily_chat_limit', 'Daily chat limit reached. Come back tomorrow to ask the advisor again.', $limit, $used);
        }
    }

    public function scansUsedToday(User $user): int
    {
        return FarmImageAnalysis::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $this->dayStart())
            ->count();
    }

    public function chatMessagesUsedToday(User $user): int
    {
        return AdvisorConversation::query()
            ->where('user_id', $user->id)
            ->where('role', 'user')
            ->where('created_at', '>=', $this->dayStart())
            ->count();
    }

    /**
     * @return array{scans:array{used:int,limit:int,remaining:int},chat:array{used:int,limit:int,remaining:int},resetsAt:string}
     */
    public function snapshot(User $user): array
    {
        $scanLimit = (int) config('security.daily_limits.scans', 4);
        $chatLimit = (int) config('security.daily_limits.chat_messages', 8);
        $scansUsed = $this->scansUsedToday($user);
        $chatUsed = $this->chatMessagesUsedToday($user);

        return [
            'scans' => [
                'used' => $scansUsed,
                'limit' => $scanLimit,
                'remaining' => max(0, $scanLimit - $scansUsed),
            ],
            'chat' => [
                'used' => $chatUsed,
                'limit' => $chatLimit,
                'remaining' => max(0, $chatLimit - $chatUsed),
            ],
            'resetsAt' => $this->dayEnd()->toIso8601String(),
        ];
    }

    private function dayStart(): Carbon
    {
        return now()->startOfDay();
    }

    private function dayEnd(): Carbon
    {
        return now()->endOfDay();
    }

    private function reject(string $code, string $message, int $limit, int $used): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
            'code' => $code,
            'limit' => $limit,
            'used' => $used,
            'remaining' => 0,
            'resetsAt' => $this->dayEnd()->toIso8601String(),
        ], 429));
    }
}
