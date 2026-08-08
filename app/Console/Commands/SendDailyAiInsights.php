<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AiAdvisorService;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyAiInsights extends Command
{
    protected $signature = 'agroaide:send-daily-ai-insights';

    protected $description = 'Send daily AI agronomy insight push notifications';

    public function __construct(
        private AiAdvisorService $advisorService,
        private NotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $users = User::query()
            ->whereNotNull('push_token')
            ->get();

        $sent = 0;

        foreach ($users as $user) {
            try {
                $insights = $this->advisorService->dailyInsight($user);
            } catch (\Throwable $e) {
                Log::warning('Daily AI insight generation failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $first = $insights[0] ?? null;
            if (! $first) {
                continue;
            }

            $title = $first['title'] ?? 'Today’s farm tip';
            $description = $first['description'] ?? 'Open AgroAide for today’s agronomy insight.';
            $dayKey = now()->toDateString();

            $notification = $this->dispatcher->notify(
                $user,
                'ai_insight',
                $title,
                $description,
                [
                    'insightId' => $first['id'] ?? 'tip-1',
                    'dayKey' => $dayKey,
                ],
                [
                    'push' => true,
                    'preference' => 'aiInsights',
                    'dedupeMinutes' => 60 * 20,
                    'dedupeKey' => 'dayKey',
                ],
            );

            if ($notification) {
                $sent++;
            }
        }

        $this->info("Sent {$sent} daily AI insight(s).");

        return self::SUCCESS;
    }
}
