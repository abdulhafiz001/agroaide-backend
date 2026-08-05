<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketPriceSnapshot extends Model
{
    protected $fillable = [
        'market_id',
        'market_name',
        'market_area',
        'market_city',
        'market_state',
        'market_lat',
        'market_lng',
        'crop_key',
        'product_id',
        'product_name',
        'product_slug',
        'unit',
        'price_avg',
        'price_min',
        'price_max',
        'currency',
        'confidence_level',
        'available',
        'source_updated_on',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'market_lat' => 'float',
            'market_lng' => 'float',
            'price_avg' => 'float',
            'price_min' => 'float',
            'price_max' => 'float',
            'available' => 'boolean',
            'source_updated_on' => 'date',
            'fetched_at' => 'datetime',
        ];
    }
}
