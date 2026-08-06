<?php

namespace Tests\Unit;

use App\Services\DiagnosisResponseParser;
use PHPUnit\Framework\TestCase;

class DiagnosisResponseParserTest extends TestCase
{
    public function test_parses_json_object(): void
    {
        $parser = new DiagnosisResponseParser;
        $parsed = $parser->parse('{"crop":"maize","condition":"healthy","confidencePercent":88,"summary":"Looks fine","disease":null,"recommendations":{"immediate":["Keep watering"]}}');

        $this->assertSame('maize', $parsed['crop']);
        $this->assertSame('healthy', $parsed['condition']);
        $this->assertSame(88, $parsed['confidencePercent']);
        $this->assertNull($parsed['disease']);
        $this->assertSame(['Keep watering'], $parsed['recommendations']['immediate']);
    }

    public function test_extracts_json_from_markdown_fence(): void
    {
        $parser = new DiagnosisResponseParser;
        $parsed = $parser->parse("```json\n{\"crop\":\"rice\",\"condition\":\"fair\",\"confidencePercent\":70,\"summary\":\"Mild stress\"}\n```");

        $this->assertSame('rice', $parsed['crop']);
        $this->assertSame('fair', $parsed['condition']);
        $this->assertSame(70, $parsed['confidencePercent']);
    }

    public function test_parses_prose_markdown_fields_from_vision_model(): void
    {
        $parser = new DiagnosisResponseParser;
        $raw = <<<'TEXT'
The image shows a maize plant with a brown spot on the leaf.

**Crop:** Maize
**Condition:** Healthy
**Disease:** None
**Confidence Percent:** 100%
**Summary:** The maize plant appears to be healthy with no visible signs of disease.
**Details:** The brown spot is likely minor injury.
**Recommendations:** No action is recommended at this time.
TEXT;

        $parsed = $parser->parse($raw);

        $this->assertSame('Maize', $parsed['crop']);
        $this->assertSame('healthy', $parsed['condition']);
        $this->assertSame(100, $parsed['confidencePercent']);
        $this->assertNull($parsed['disease']);
        $this->assertStringContainsString('healthy', strtolower($parsed['summary']));
        $this->assertNotEmpty($parsed['recommendations']['immediate']);
    }
}
