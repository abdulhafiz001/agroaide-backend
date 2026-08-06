<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InputEstimateHistory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'result_json' => 'array',
            'area_m2' => 'float',
            'row_cm' => 'float',
            'intra_cm' => 'float',
            'population' => 'integer',
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
}
