<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    protected $fillable = [
        'user_id',
        'terms_version',
        'privacy_version',
        'research_version',
        'research_consent',
        'consented_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'research_consent' => 'boolean',
            'consented_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
