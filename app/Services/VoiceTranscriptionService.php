<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoiceTranscriptionService
{
    private string $apiKey;
    private string $endpoint;
    private string $model;

    public function __construct()
    {
        $this->apiKey = trim(config('services.groq.api_key') ?? env('GROQ_API_KEY', ''));
        $this->endpoint = config('services.groq.endpoint') ?? 'https://api.groq.com/openai/v1/audio/transcriptions';
        $this->model = config('services.groq.model') ?? 'whisper-large-v3';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Transcribe audio from base64-encoded data.
     * Supports m4a, wav, mp3, webm, ogg formats.
     */
    public function transcribe(string $audioBase64, string $languageHint = 'en'): array
    {
        if (! $this->isAvailable()) {
            return ['success' => false, 'error' => 'Voice transcription is not configured. Set GROQ_API_KEY in backend .env (free at groq.com).'];
        }

        try {
            $audioData = $this->extractRawBase64($audioBase64);
            $decoded = base64_decode($audioData);
            if (! $decoded) {
                return ['success' => false, 'error' => 'Invalid audio data.'];
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'voice_') . '.m4a';
            file_put_contents($tempPath, $decoded);

            $langMap = ['en' => 'en', 'ha' => 'ha', 'yo' => 'yo', 'pcm' => 'en'];
            $whisperLang = $langMap[$languageHint] ?? 'en';

            $response = Http::timeout(30)
                ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
                ->attach('file', file_get_contents($tempPath), 'audio.m4a')
                ->post($this->endpoint, [
                    'model' => $this->model,
                    'language' => $whisperLang,
                    'response_format' => 'json',
                ]);

            @unlink($tempPath);

            if ($response->successful()) {
                $text = $response->json('text') ?? '';
                return ['success' => true, 'text' => trim($text)];
            }

            Log::warning('Groq Whisper API error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'error' => 'Transcription failed. Please try again.'];
        } catch (\Exception $e) {
            Log::error('Voice transcription exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Voice processing error. Please try again.'];
        }
    }

    private function extractRawBase64(string $input): string
    {
        if (str_contains($input, ',')) {
            return explode(',', $input, 2)[1];
        }
        return $input;
    }
}
