<?php

namespace Tests\Unit;

use App\Support\MediaPayloadValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MediaPayloadValidatorTest extends TestCase
{
    public function test_accepts_valid_png_and_detects_its_real_type(): void
    {
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $result = app(MediaPayloadValidator::class)->image('data:image/png;base64,'.$png);

        $this->assertSame('image/png', $result['mime']);
        $this->assertSame('png', $result['extension']);
    }

    public function test_rejects_non_strict_base64_before_external_use(): void
    {
        $this->expectException(ValidationException::class);
        app(MediaPayloadValidator::class)->image('data:image/png;base64,not_base64!');
    }

    public function test_rejects_audio_whose_declared_type_disagrees_with_magic_bytes(): void
    {
        $wav = 'RIFF'.pack('V', 36).'WAVEfmt '.str_repeat("\0", 32);

        $this->expectException(ValidationException::class);
        app(MediaPayloadValidator::class)->audio('data:audio/mpeg;base64,'.base64_encode($wav));
    }
}
