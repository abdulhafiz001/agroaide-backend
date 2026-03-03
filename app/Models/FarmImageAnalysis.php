<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmImageAnalysis extends Model
{
    protected $fillable = [
        'user_id',
        'farm_field_id',
        'image_path',
        'condition',
        'result_json',
    ];

    protected function casts(): array
    {
        return [
            'result_json' => 'array',
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
