<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanFeedback extends Model
{
    protected $table = 'scan_feedback';

    protected $guarded = [];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(FarmImageAnalysis::class, 'farm_image_analysis_id');
    }
}
