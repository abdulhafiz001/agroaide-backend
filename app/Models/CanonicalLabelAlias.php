<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanonicalLabelAlias extends Model
{
    protected $guarded = [];

    public function label(): BelongsTo
    {
        return $this->belongsTo(CanonicalLabel::class, 'canonical_label_id');
    }
}
