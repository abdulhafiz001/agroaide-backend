<?php

namespace App\Services;

use App\Models\FarmImageAnalysis;
use App\Models\ScanReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScanVerificationService
{
    private const TRANSITIONS = [
        'needs_retake' => ['pending_review', 'disputed'],
        'pending_review' => ['expert_verified', 'expert_rejected', 'disputed'],
        'auto_verified' => ['expert_verified', 'expert_rejected', 'disputed'],
        'expert_verified' => ['expert_rejected', 'disputed'],
        'expert_rejected' => ['pending_review'],
        'disputed' => ['pending_review', 'expert_verified', 'expert_rejected'],
    ];

    /**
     * @param  bool  $researchBacked  Kindwise (or similar) research ID — farmers should not wait for expert review.
     */
    public function initialState(float $confidence, bool $canonicalAndValid, bool $researchBacked = false): string
    {
        if ($confidence < 0.35) {
            return 'needs_retake';
        }

        // Research-backed crop.health IDs are shown as complete to farmers.
        // Staff can still review disputes / incorrect feedback later.
        if ($researchBacked) {
            return 'auto_verified';
        }

        if ($confidence < 0.85 || ! $canonicalAndValid) {
            return 'pending_review';
        }

        return 'auto_verified';
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function transition(
        FarmImageAnalysis $scan,
        string $to,
        ?User $actor,
        ?int $cropLabelId = null,
        ?int $diseaseLabelId = null,
        ?string $reason = null,
    ): FarmImageAnalysis {
        if (! $this->canTransition($scan->verification_state, $to)) {
            throw new InvalidArgumentException("Illegal scan transition {$scan->verification_state} -> {$to}.");
        }

        return DB::transaction(function () use ($scan, $to, $actor, $cropLabelId, $diseaseLabelId, $reason) {
            $from = $scan->verification_state;
            $scan->forceFill([
                'verification_state' => $to,
                'effective_crop_label_id' => $cropLabelId ?? $scan->effective_crop_label_id,
                'effective_disease_label_id' => $diseaseLabelId ?? $scan->effective_disease_label_id,
                'outbreak_eligible' => in_array($to, ['auto_verified', 'expert_verified'], true)
                    && ($diseaseLabelId ?? $scan->effective_disease_label_id) !== null,
            ])->save();
            ScanReview::create([
                'farm_image_analysis_id' => $scan->id,
                'actor_user_id' => $actor?->id,
                'from_state' => $from,
                'to_state' => $to,
                'effective_crop_label_id' => $scan->effective_crop_label_id,
                'effective_disease_label_id' => $scan->effective_disease_label_id,
                'reason' => $reason,
            ]);

            return $scan->refresh();
        });
    }
}
