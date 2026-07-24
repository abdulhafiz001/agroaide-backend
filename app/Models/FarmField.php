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
        'client_uuid',
        'health_percentage',
        'moisture_percentage',
        'planted_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'area_m2' => 'float',
            'boundary_geojson' => 'array',
            'boundary_updated_at' => 'datetime',
            'health_percentage' => 'integer',
            'moisture_percentage' => 'integer',
            'planted_at' => 'date',
        ];
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
