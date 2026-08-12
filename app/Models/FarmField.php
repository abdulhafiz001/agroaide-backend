<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmField extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'crop',
        'area_m2',
        'boundary_geojson',
        'boundary_updated_at',
        'boundary_reminder_sent_at',
        'client_uuid',
        'health_percentage',
        'moisture_percentage',
        'planted_at',
        'harvest_start_date',
        'harvest_end_date',
        'planted_at_recorded_at',
        'harvest_estimate_notified_at',
        'harvest_reminder_sent_at',
        'harvested_at',
        'yield_note',
        'planned_next_crop',
        'planned_plant_at',
        'next_plant_remind_2d_sent_at',
        'next_plant_remind_on_sent_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'area_m2' => 'float',
            'boundary_geojson' => 'array',
            'boundary_updated_at' => 'datetime',
            'boundary_reminder_sent_at' => 'datetime',
            'health_percentage' => 'integer',
            'moisture_percentage' => 'integer',
            'planted_at' => 'date',
            'harvest_start_date' => 'date',
            'harvest_end_date' => 'date',
            'planted_at_recorded_at' => 'datetime',
            'harvest_estimate_notified_at' => 'datetime',
            'harvest_reminder_sent_at' => 'datetime',
            'harvested_at' => 'date',
            'planned_plant_at' => 'date',
            'next_plant_remind_2d_sent_at' => 'datetime',
            'next_plant_remind_on_sent_at' => 'datetime',
        ];
    }

    public function isHarvestWindowActive(?\Carbon\CarbonInterface $on = null): bool
    {
        if (! $this->harvest_start_date || ! $this->harvest_end_date || $this->harvested_at) {
            return false;
        }

        $day = ($on ?? now())->toDateString();

        return $day >= $this->harvest_start_date->toDateString()
            && $day <= $this->harvest_end_date->toDateString();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FieldTransaction::class);
    }

    public function getDaysSincePlantingAttribute(): ?int
    {
        if (! $this->planted_at) {
            return null;
        }

        return $this->planted_at->diffInDays(now());
    }
}
