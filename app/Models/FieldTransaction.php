<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'farm_field_id',
        'client_uuid',
        'type',
        'category',
        'amount',
        'quantity',
        'unit',
        'occurred_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'quantity' => 'float',
            'occurred_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function farmField(): BelongsTo
    {
        return $this->belongsTo(FarmField::class);
    }
}
