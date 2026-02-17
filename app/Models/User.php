<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'farm_name',
        'farm_location',
        'farm_latitude',
        'farm_longitude',
        'farm_size_hectares',
        'crops',
        'experience_level',
        'soil_type',
        'irrigation_access',
        'avatar_color',
        'preferred_theme',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'crops' => 'array',
            'farm_size_hectares' => 'float',
            'farm_latitude' => 'float',
            'farm_longitude' => 'float',
        ];
    }

    public function farmFields(): HasMany
    {
        return $this->hasMany(FarmField::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function calendarTasks(): HasMany
    {
        return $this->hasMany(CalendarTask::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function advisorConversations(): HasMany
    {
        return $this->hasMany(AdvisorConversation::class);
    }
}
