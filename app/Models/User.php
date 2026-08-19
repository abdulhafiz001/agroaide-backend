<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone_number',
        'phone_normalized',
        'farm_name',
        'farm_location',
        'farm_latitude',
        'farm_longitude',
        'farm_size_m2',
        'crops',
        'experience_level',
        'soil_type',
        'irrigation_access',
        'avatar_color',
        'preferred_theme',
        'preferred_language',
        'push_token',
        'notification_preferences',
        'last_planting_prompt_on',
        'app_rating_prompt_status',
        'ai_response_depth',
        'ai_risk_tolerance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'push_token',
        'phone_normalized',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'crops' => 'array',
            'notification_preferences' => 'array',
            'last_planting_prompt_on' => 'date',
            'farm_size_m2' => 'float',
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

    public function farmImageAnalyses(): HasMany
    {
        return $this->hasMany(FarmImageAnalysis::class);
    }

    public function fieldTransactions(): HasMany
    {
        return $this->hasMany(FieldTransaction::class);
    }

    public function cropWatches(): HasMany
    {
        return $this->hasMany(CropWatch::class);
    }

    public function syncActionLogs(): HasMany
    {
        return $this->hasMany(SyncActionLog::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    public function hasFarmCoordinates(): bool
    {
        return $this->farm_latitude !== null && $this->farm_longitude !== null;
    }

    /**
     * Exact farm GPS used for weather, planting windows, and location-aware alerts.
     *
     * @return array{latitude: float, longitude: float, label: string}|null
     */
    public function farmCoordinates(): ?array
    {
        if (! $this->hasFarmCoordinates()) {
            return null;
        }

        $label = trim((string) ($this->farm_location ?: $this->farm_name ?: ''));

        return [
            'latitude' => (float) $this->farm_latitude,
            'longitude' => (float) $this->farm_longitude,
            'label' => $label !== '' ? $label : 'your farm',
        ];
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['agronomist', 'admin'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
