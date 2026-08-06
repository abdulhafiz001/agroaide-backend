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
        $this->endpoint = config('services.groq.transcription_endpoint') ?? 'https://api.groq.com/openai/v1/audio/transcriptions';
        $this->model = config('services.groq.transcription_model') ?? 'whisper-large-v3';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * @param  array{bytes:string,mime:string,extension:string}  $media
     */
    public function transcribe(array $media, string $languageHint = 'en'): array
    {
        if (! $this->isAvailable()) {
            return [
                'success' => false,
                'error' => 'Voice transcription is not configured. Add GROQ_API_KEY to agroaide-backend/.env (free key at https://console.groq.com/keys), then restart the backend.',
            ];
        }

        $tempPath = null;
        try {
            $basePath = tempnam(sys_get_temp_dir(), 'voice_');
            if ($basePath === false) {
                return ['success' => false, 'error' => 'Voice processing error. Please try again.'];
            }
            $tempPath = $basePath.'.'.$media['extension'];
            rename($basePath, $tempPath);
            file_put_contents($tempPath, $media['bytes']);

            $langMap = ['en' => 'en', 'ha' => 'ha', 'yo' => 'yo', 'pcm' => 'en'];
            $whisperLang = $langMap[$languageHint] ?? 'en';

            $response = Http::timeout(30)
                ->withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
                ->attach('file', file_get_contents($tempPath), 'audio.'.$media['extension'], ['Content-Type' => $media['mime']])
                ->post($this->endpoint, [
                    'model' => $this->model,
                    'language' => $whisperLang,
                    'response_format' => 'json',
                ]);

            if ($response->successful()) {
                $text = $response->json('text') ?? '';

                return ['success' => true, 'text' => trim($text)];
            }

            Log::warning('Groq Whisper API error', ['status' => $response->status()]);

            return ['success' => false, 'error' => 'Transcription failed. Please try again.'];
        } catch (\Exception $e) {
            Log::error('Voice transcription exception', ['exception' => $e::class]);

            return ['success' => false, 'error' => 'Voice processing error. Please try again.'];
        } finally {
            if (is_string($tempPath) && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}
