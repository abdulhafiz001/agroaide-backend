<?php

namespace Tests\Feature;

use App\Models\AdvisorConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiAdvisorScopeAndResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_retired_gemini_2_0_flash_model_is_automatically_upgraded_to_gemini_2_5_flash(): void
    {
        config([
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.text_model' => 'gemini-2.0-flash',
            'services.groq.api_key' => '',
            'services.nvidia.api_key' => '',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "- **Maize Plot:** Water deeply this morning before the heat builds."],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'farm_name' => 'Kano Sunrise Farm',
            'crops' => ['maize', 'cowpea'],
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/advisor/chat', [
            'message' => 'How should I care for my maize today?',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('- **Maize Plot:** Water deeply this morning', $response->json('reply'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')
                && str_contains(
                    (string) data_get($request->data(), 'systemInstruction.parts.0.text', ''),
                    'STRICT FARM-ONLY SCOPE',
                );
        });
    }

    public function test_off_topic_general_chatbot_questions_are_refused_and_redirected_to_users_farm(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'farm_name' => 'Green Harvest Farm',
            'crops' => ['maize', 'cassava'],
            'preferred_language' => 'en',
        ]);
        Sanctum::actingAs($user);

        $offTopicQuestions = [
            'Who is the president of Nigeria?',
            'Write python code for a calculator app',
            'Who won the Champions League last night?',
            'What is the capital of France?',
        ];

        foreach ($offTopicQuestions as $question) {
            $response = $this->postJson('/api/advisor/chat', [
                'message' => $question,
            ]);

            $response->assertOk();
            $reply = (string) $response->json('reply');
            $this->assertStringContainsString('Green Harvest Farm', $reply);
            $this->assertStringContainsString('maize, cassava', $reply);
            $this->assertStringContainsString('only answer questions related to your farm', $reply);
        }

        Http::assertNothingSent();
    }

    public function test_poisoned_unavailable_messages_are_hidden_from_history_and_fallback_uses_farm_context(): void
    {
        config([
            'services.gemini.api_key' => 'test-gemini-key',
            'services.groq.api_key' => '',
            'services.nvidia.api_key' => '',
        ]);

        Http::fake([
            '*' => Http::response(['error' => ['message' => 'service down']], 503),
        ]);

        $user = User::factory()->create([
            'name' => 'Abdul',
            'farm_name' => 'Zaria Agro Plot',
            'farm_location' => 'Zaria, Kaduna',
            'crops' => ['maize', 'tomato'],
        ]);

        AdvisorConversation::create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'message' => "I'm temporarily unavailable. Please try again shortly.",
        ]);

        Sanctum::actingAs($user);

        $historyBefore = $this->getJson('/api/advisor/history')->assertOk()->json('messages');
        $this->assertCount(0, $historyBefore);

        $chatResponse = $this->postJson('/api/advisor/chat', [
            'message' => 'What should I do on my farm today?',
        ])->assertOk();

        $reply = (string) $chatResponse->json('reply');
        $this->assertStringNotContainsString('temporarily unavailable', strtolower($reply));
        $this->assertStringContainsString('Zaria Agro Plot', $reply);
    }
}
