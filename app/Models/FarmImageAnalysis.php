<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmImageAnalysis extends Model
{
    protected $fillable = [
        'user_id',
        'farm_field_id',
        'latitude',
        'longitude',
        'image_path',
        'condition',
        'disease_name',
        'result_json',
        'model_version_id',
        'prompt_version_id',
        'confidence_policy_id',
        'predicted_crop_label_id',
        'predicted_disease_label_id',
        'effective_crop_label_id',
        'effective_disease_label_id',
        'processing_state',
        'verification_state',
        'normalized_confidence',
        'inference_latency_ms',
        'raw_result',
        'raw_result_checksum',
        'outbreak_eligible',
        'processing_started_at',
        'processing_completed_at',
        'safe_error_code',
    ];

    protected function casts(): array
    {
        return [
            'result_json' => 'array',
            'normalized_confidence' => 'float',
            'outbreak_eligible' => 'boolean',
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function farmField(): BelongsTo
    {
        return $this->belongsTo(FarmField::class);
    }

    public function predictedCropLabel(): BelongsTo
    {
        return $this->belongsTo(CanonicalLabel::class, 'predicted_crop_label_id');
    }

    public function predictedDiseaseLabel(): BelongsTo
    {
        return $this->belongsTo(CanonicalLabel::class, 'predicted_disease_label_id');
    }

    public function effectiveDiseaseLabel(): BelongsTo
    {
        return $this->belongsTo(CanonicalLabel::class, 'effective_disease_label_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(ScanFeedback::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ScanReview::class);
    }
}
