<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAfterCreation;
use Illuminate\Database\Eloquent\Model;

class ConfidencePolicy extends Model
{
    use ImmutableAfterCreation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'retake_below' => 'float',
            'review_below' => 'float',
            'require_canonical' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
