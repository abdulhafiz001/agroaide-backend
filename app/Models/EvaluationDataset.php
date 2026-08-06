<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class EvaluationDataset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $dataset): void {
            if ($dataset->getOriginal('locked_at') !== null) {
                throw new LogicException('Locked evaluation datasets are immutable.');
            }
        });
        static::deleting(fn (self $dataset) => $dataset->locked_at
            ? throw new LogicException('Locked evaluation datasets are immutable.')
            : null);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EvaluationDatasetItem::class);
    }
}
