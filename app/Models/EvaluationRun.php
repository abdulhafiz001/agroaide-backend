<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class EvaluationRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $run): void {
            if ($run->getOriginal('status') === 'completed') {
                throw new LogicException('Completed evaluation runs are immutable.');
            }
        });
        static::deleting(fn (self $run) => $run->status === 'completed'
            ? throw new LogicException('Completed evaluation runs are immutable.')
            : null);
    }
}
