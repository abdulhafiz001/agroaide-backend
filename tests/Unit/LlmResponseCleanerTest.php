<?php

namespace Tests\Unit;

use App\Services\LlmResponseCleaner;
use PHPUnit\Framework\TestCase;

class LlmResponseCleanerTest extends TestCase
{
    public function test_strips_think_blocks(): void
    {
        $cleaner = new LlmResponseCleaner;
        $raw = "<think>secret plan</think>\n\n**Water** your maize today.";
        $this->assertSame('**Water** your maize today.', $cleaner->clean($raw));
    }

    public function test_strips_thinking_process_preamble(): void
    {
        $cleaner = new LlmResponseCleaner;
        $raw = "Here's a thinking process:\n**Analyze User Input:**\n- Crop: Cassava\n\nFinal answer: Plant about 1586 stands. Guide only - may not be 100% correct for your soil and variety.";
        $cleaned = $cleaner->clean($raw);
        $this->assertStringContainsString('1586', $cleaned);
        $this->assertStringNotContainsString('thinking process', strtolower($cleaned));
        $this->assertStringNotContainsString('Analyze User Input', $cleaned);
    }
}
