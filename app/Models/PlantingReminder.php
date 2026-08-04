<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantingReminder extends Model
{
    protected $fillable = [
        'user_id',
        'crop_watch_id',
        'notification_id',
        'crop',
        'plant_on',
        'remind_2d_at',
        'remind_on_at',
        'sent_2d_at',
        'sent_on_at',
        'local_scheduled',
    ];

    protected function casts(): array
    {
        return [
            'plant_on' => 'date',
            'remind_2d_at' => 'datetime',
            'remind_on_at' => 'datetime',
            'sent_2d_at' => 'datetime',
            'sent_on_at' => 'datetime',
            'local_scheduled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cropWatch(): BelongsTo
    {
        return $this->belongsTo(CropWatch::class);
    }
}
