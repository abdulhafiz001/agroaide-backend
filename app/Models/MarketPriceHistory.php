<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketPriceHistory extends Model
{
    protected $table = 'market_price_history';

    protected $fillable = [
        'market_id',
        'crop_key',
        'product_id',
        'unit',
        'price_avg',
        'currency',
        'recorded_on',
    ];

    protected function casts(): array
    {
        return [
            'price_avg' => 'float',
            'recorded_on' => 'date',
        ];
    }
}
