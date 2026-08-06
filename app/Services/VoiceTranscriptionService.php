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
        $this->apiKey = trim((string) (config('services.groq.api_key') ?? ''));
        $this->endpoint = (string) (config('services.groq.transcription_endpoint') ?? 'https://api.groq.com/openai/v1/audio/transcriptions');
        $this->model = (string) (config('services.groq.transcription_model') ?? 'whisper-large-v3');
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param  array{bytes:string,mime:string,extension:string}  $media
     */
    public function transcribe(array $media, string $languageHint = 'en'): array
    {
        if (! $this->isAvailable()) {
            return [
                'success' => false,
                'error' => 'Voice transcription is not configured. Add GROQ_API_KEY to agroaide-backend/.env, then restart the backend.',
            ];
        }

        if (($media['bytes'] ?? '') === '' || strlen($media['bytes']) < 64) {
            return ['success' => false, 'error' => 'Recording was too short. Hold the mic a bit longer, then stop.'];
        }

        $tempPath = null;
        try {
            $extension = $this->normalizeExtension((string) ($media['extension'] ?? 'm4a'));
            $mime = $this->normalizeMime((string) ($media['mime'] ?? 'audio/mp4'), $extension);

            $basePath = tempnam(sys_get_temp_dir(), 'voice_');
            if ($basePath === false) {
                return ['success' => false, 'error' => 'Voice processing error. Please try again.'];
            }
            $tempPath = $basePath.'.'.$extension;
            rename($basePath, $tempPath);
            file_put_contents($tempPath, $media['bytes']);

            // Whisper language codes: leave unset for Pidgin / uncertain hints so Groq auto-detects.
            $langMap = ['en' => 'en', 'ha' => 'ha', 'yo' => 'yo'];
            $whisperLang = $langMap[$languageHint] ?? null;

            $request = Http::timeout(45)
                ->withToken($this->apiKey)
                ->attach('file', file_get_contents($tempPath), 'audio.'.$extension, ['Content-Type' => $mime]);

            $fields = [
                'model' => $this->model,
                'response_format' => 'json',
                'temperature' => '0',
            ];
            if ($whisperLang !== null) {
                $fields['language'] = $whisperLang;
            }

            $response = $request->post($this->endpoint, $fields);

            if ($response->successful()) {
                $text = trim((string) ($response->json('text') ?? ''));
                if ($text === '') {
                    return ['success' => false, 'error' => 'No speech detected. Try again closer to the mic.'];
                }

                return ['success' => true, 'text' => $text];
            }

            Log::warning('Groq Whisper API error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 400),
                'mime' => $mime,
                'extension' => $extension,
                'bytes' => strlen($media['bytes']),
            ]);

            $providerMessage = (string) data_get($response->json(), 'error.message', '');
            if ($response->status() === 400 && str_contains(strtolower($providerMessage), 'format')) {
                return ['success' => false, 'error' => 'Unsupported audio format. Please try recording again.'];
            }

            return ['success' => false, 'error' => 'Transcription failed. Please try again.'];
        } catch (\Throwable $e) {
            Log::error('Voice transcription exception', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Voice processing error. Please try again.'];
        } finally {
            if (is_string($tempPath) && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function normalizeExtension(string $extension): string
    {
        $extension = strtolower(ltrim($extension, '.'));

        return match ($extension) {
            'mp4', 'm4a', 'aac', 'caf' => 'm4a',
            'mpeg', 'mpga', 'mp3' => 'mp3',
            'wav', 'x-wav' => 'wav',
            'ogg', 'oga' => 'ogg',
            'webm' => 'webm',
            '3gp', '3gpp' => '3gp',
            default => $extension !== '' ? $extension : 'm4a',
        };
    }

    private function normalizeMime(string $mime, string $extension): string
    {
        $mime = strtolower(trim($mime));

        return match (true) {
            str_contains($mime, 'wav') => 'audio/wav',
            str_contains($mime, 'mpeg') || str_contains($mime, 'mp3') => 'audio/mpeg',
            str_contains($mime, 'ogg') => 'audio/ogg',
            str_contains($mime, 'webm') => 'audio/webm',
            str_contains($mime, '3gp') => 'audio/3gpp',
            str_contains($mime, 'mp4')
                || str_contains($mime, 'm4a')
                || str_contains($mime, 'aac')
                || str_contains($mime, 'caf') => 'audio/mp4',
            default => match ($extension) {
                'wav' => 'audio/wav',
                'mp3' => 'audio/mpeg',
                'ogg' => 'audio/ogg',
                'webm' => 'audio/webm',
                '3gp' => 'audio/3gpp',
                default => 'audio/mp4',
            },
        };
    }
}
