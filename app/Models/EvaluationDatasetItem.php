<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAfterCreation;
use Illuminate\Database\Eloquent\Model;

class EvaluationDatasetItem extends Model
{
    use ImmutableAfterCreation;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
