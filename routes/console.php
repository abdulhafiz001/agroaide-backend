<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('agroaide:detect-outbreaks')->hourly();
Schedule::command('agroaide:send-task-reminders')->everyThirtyMinutes();
Schedule::command('agroaide:send-weather-alerts')->everyTwoHours();
Schedule::command('agroaide:send-daily-ai-insights')->dailyAt('06:30');
