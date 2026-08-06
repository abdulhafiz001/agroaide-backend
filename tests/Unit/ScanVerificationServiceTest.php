<?php

namespace Tests\Unit;

use App\Services\ScanVerificationService;
use PHPUnit\Framework\TestCase;

class ScanVerificationServiceTest extends TestCase
{
    public function test_exact_confidence_boundaries_match_the_approved_policy(): void
    {
        $service = new ScanVerificationService;

        $this->assertSame('needs_retake', $service->initialState(0.5999, true));
        $this->assertSame('pending_review', $service->initialState(0.60, true));
        $this->assertSame('pending_review', $service->initialState(0.8499, true));
        $this->assertSame('auto_verified', $service->initialState(0.85, true));
        $this->assertSame('pending_review', $service->initialState(0.99, false));
    }

    public function test_only_approved_transitions_are_legal(): void
    {
        $service = new ScanVerificationService;

        $this->assertTrue($service->canTransition('pending_review', 'expert_verified'));
        $this->assertTrue($service->canTransition('auto_verified', 'disputed'));
        $this->assertTrue($service->canTransition('expert_rejected', 'pending_review'));
        $this->assertFalse($service->canTransition('expert_verified', 'auto_verified'));
        $this->assertFalse($service->canTransition('needs_retake', 'expert_verified'));
    }
}
