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

    public function test_strips_unclosed_think_dump_like_crop_watch(): void
    {
        $cleaner = new LlmResponseCleaner;
        $raw = <<<'TEXT'
<think>
Thinking Process:
1. **Deconstruct the Request:**
*   **Goal:** Write 2 short sentences for a Nigerian farmer.
*   **Language:** English.
*   **Kind:** window_open
*   **Crop:** Tomato.
*   **Location:** Municipal Area Council, Federal Capital.
Suggested planting date: 2026-09-01
TEXT;

        $this->assertSame('', $cleaner->clean($raw));
        $this->assertSame(
            'Good time to plant Tomato around your farm.',
            $cleaner->farmerFacing($raw, 'Good time to plant Tomato around your farm.'),
        );
    }

    public function test_detects_reasoning_needles(): void
    {
        $cleaner = new LlmResponseCleaner;
        $this->assertTrue($cleaner->looksLikeReasoning('1. **Deconstruct the Request:** Goal: write sentences'));
        $this->assertFalse($cleaner->looksLikeReasoning('Good time to plant tomato around Abuja. Best date is 1 September 2026.'));
    }
}
