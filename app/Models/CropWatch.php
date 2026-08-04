<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CropWatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'crop',
        'notify_when_planting_window',
        'status',
        'last_notified_on',
        'best_plant_date',
        'last_analysis_status',
        'last_analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'notify_when_planting_window' => 'boolean',
            'last_notified_on' => 'date',
            'best_plant_date' => 'date',
            'last_analyzed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
