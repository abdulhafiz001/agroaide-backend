<?php

namespace App\Models\Concerns;

use LogicException;

trait ImmutableAfterCreation
{
    protected static function bootImmutableAfterCreation(): void
    {
        static::updating(fn () => throw new LogicException(static::class.' records are immutable.'));
        static::deleting(fn () => throw new LogicException(static::class.' records are immutable.'));
    }
}
