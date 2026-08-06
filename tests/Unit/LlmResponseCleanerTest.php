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
}
