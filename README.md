# AgroAide API (Laravel)

Backend for the AgroAide mobile farm assistant: auth, farm records, weather, AI advisor, crop scanning, disease-outbreak clustering, and FCM notifications.

## Stack

- PHP 8.2+ / Laravel 12
- Laravel Sanctum (API tokens)
- MySQL (or SQLite for local/testing)
- Open-Meteo, GitHub Models, Groq Whisper, optional PlantNet, Firebase FCM

## Quick start

```bash
cp .env.example .env
php artisan key:generate
# configure DB_* and API keys in .env
composer install
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```

For scheduled alerts (weather, tasks, outbreaks):

```bash
php artisan schedule:work
```

## Environment setup (secrets)

Copy `.env.example` → `.env`. **Never commit** `.env` or service-account JSON.

| Variable | Purpose |
|----------|---------|
| `APP_KEY` | Laravel encryption key |
| `DB_*` | Database |
| `GITHUB_MODELS_API_KEY` | LLM + vision (advisor, scan) |
| `GROQ_API_KEY` | Voice transcription ([console.groq.com](https://console.groq.com/keys)) |
| `PLANTNET_API_KEY` | Optional plant ID assist |
| `FCM_PROJECT_ID` | Firebase project id |
| `FCM_CREDENTIALS_PATH` | Path to Firebase service account JSON (gitignored) |
| `MAIL_*` | Welcome / password-reset email |

See also: [`docs/FIREBASE_FCM_SETUP.md`](../docs/FIREBASE_FCM_SETUP.md), [`docs/ARCHITECTURE.md`](../docs/ARCHITECTURE.md).

## Tests

```bash
php artisan test
```

Focused suites: outbreak distance, auth API, scan history ownership.

## Useful artisan commands

```bash
php artisan agroaide:detect-outbreaks
php artisan agroaide:send-weather-alerts
php artisan agroaide:send-task-reminders
php artisan agroaide:test-outbreak-notification --email=you@example.com
```

## API health

`GET /api/health` → `{ "ok": true }`
