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
        'last_notified_on',
    ];

    protected function casts(): array
    {
        return [
            'notify_when_planting_window' => 'boolean',
            'last_notified_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
