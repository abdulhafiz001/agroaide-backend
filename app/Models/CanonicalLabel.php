<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanonicalLabel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_diseased' => 'boolean', 'active' => 'boolean'];
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(self::class, 'crop_label_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CanonicalLabelAlias::class);
    }
}
