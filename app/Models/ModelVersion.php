<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAfterCreation;
use Illuminate\Database\Eloquent\Model;

class ModelVersion extends Model
{
    use ImmutableAfterCreation;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['parameters' => 'array', 'active' => 'boolean'];
    }
}
