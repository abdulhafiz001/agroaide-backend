<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class MediaPayloadValidator
{
    /** @return array{bytes:string,mime:string,extension:string,dataUrl:string} */
    public function image(string $input): array
    {
        [$declaredMime, $encoded] = $this->parts($input);
        $bytes = $this->decode($encoded, (int) config('security.media.image_max_bytes'));
        $info = @getimagesizefromstring($bytes);

        if (! $info || empty($info['mime']) || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->invalid('imageBase64', 'The image must be a valid JPEG, PNG, or WebP file.');
        }
        if ($declaredMime !== null && $declaredMime !== $info['mime']) {
            $this->invalid('imageBase64', 'The image media type does not match its contents.');
        }
        if (($info[0] * $info[1]) > (int) config('security.media.image_max_pixels')) {
            $this->invalid('imageBase64', 'The image dimensions are too large.');
        }

        $extension = match ($info['mime']) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return [
            'bytes' => $bytes,
            'mime' => $info['mime'],
            'extension' => $extension,
            'dataUrl' => "data:{$info['mime']};base64,".base64_encode($bytes),
        ];
    }

    /** @return array{bytes:string,mime:string,extension:string} */
    public function audio(string $input): array
    {
        [$declaredMime, $encoded] = $this->parts($input);
        $bytes = $this->decode($encoded, (int) config('security.media.audio_max_bytes'));
        [$mime, $extension] = $this->detectAudio($bytes);
        // Expo/RN FileReader often labels blobs as application/octet-stream — trust magic bytes then.
        $ignoreDeclared = $declaredMime === null
            || in_array($declaredMime, ['application/octet-stream', 'binary/octet-stream', 'application/zip'], true);

        if ($mime === null || (! $ignoreDeclared && ! $this->compatibleAudioMime($declaredMime, $mime))) {
            $this->invalid('audioBase64', 'The audio must be a valid MP3, WAV, M4A, OGG, or WebM file.');
        }

        return ['bytes' => $bytes, 'mime' => $mime, 'extension' => $extension];
    }

    /** @return array{0:?string,1:string} */
    private function parts(string $input): array
    {
        if (! str_starts_with($input, 'data:')) {
            return [null, preg_replace('/\s+/', '', $input) ?? ''];
        }
        if (! preg_match('/^data:([a-z0-9.+-]+\/[a-z0-9.+-]+);base64,([A-Za-z0-9+\/=\r\n]+)$/i', $input, $match)) {
            $this->invalid('media', 'The media payload is malformed.');
        }

        return [strtolower($match[1]), preg_replace('/\s+/', '', $match[2]) ?? ''];
    }

    private function decode(string $encoded, int $maxBytes): string
    {
        if ($encoded === '' || strlen($encoded) > (int) ceil($maxBytes * 4 / 3) + 4) {
            $this->invalid('media', 'The media payload is too large.');
        }
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || strlen($bytes) > $maxBytes) {
            $this->invalid('media', 'The media payload is invalid or too large.');
        }

        return $bytes;
    }

    /** @return array{0:?string,1:?string} */
    private function detectAudio(string $bytes): array
    {
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WAVE') {
            return ['audio/wav', 'wav'];
        }
        if (str_starts_with($bytes, 'OggS')) {
            return ['audio/ogg', 'ogg'];
        }
        if (str_starts_with($bytes, "\x1A\x45\xDF\xA3")) {
            return ['audio/webm', 'webm'];
        }
        if (substr($bytes, 4, 4) === 'ftyp') {
            return ['audio/mp4', 'm4a'];
        }
        // 3GP family (common on Android recorders)
        if (str_starts_with($bytes, "\x00\x00\x00") && str_contains(substr($bytes, 0, 16), '3gp')) {
            return ['audio/3gpp', '3gp'];
        }
        if (str_starts_with($bytes, 'ID3') || (strlen($bytes) > 2 && ord($bytes[0]) === 0xFF && (ord($bytes[1]) & 0xE0) === 0xE0)) {
            return ['audio/mpeg', 'mp3'];
        }

        return [null, null];
    }

    private function compatibleAudioMime(string $declared, string $detected): bool
    {
        $aliases = [
            'audio/x-wav' => 'audio/wav',
            'audio/mp4a-latm' => 'audio/mp4',
            'audio/m4a' => 'audio/mp4',
            'audio/x-m4a' => 'audio/mp4',
            'audio/aac' => 'audio/mp4',
            'audio/mp4' => 'audio/mp4',
            'video/mp4' => 'audio/mp4',
            'video/webm' => 'audio/webm',
            'audio/3gpp' => 'audio/3gpp',
            'audio/3gp' => 'audio/3gpp',
            'video/3gpp' => 'audio/3gpp',
        ];
        $declared = $aliases[$declared] ?? $declared;
        $detected = $aliases[$detected] ?? $detected;

        // Expo often declares audio/m4a / audio/mp4 interchangeably with ftyp containers.
        $mp4Family = ['audio/mp4', 'audio/3gpp'];
        if (in_array($declared, $mp4Family, true) && in_array($detected, $mp4Family, true)) {
            return true;
        }

        return $declared === $detected;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
