# AgroAide API (Laravel)

Backend for the AgroAide mobile farm assistant: auth, farm records, weather, AI advisor, crop scanning, disease-outbreak clustering, and FCM notifications.

## Stack

- PHP 8.2+ / Laravel 12
- Laravel Sanctum (API tokens)
- MySQL (or SQLite for local/testing)
- Open-Meteo, Groq vision/text/Whisper, optional PlantNet, Firebase FCM

## Quick start

```bash
cp .env.example .env
php artisan key:generate
# configure DB_* and API keys in .env
composer install
php artisan migrate
php artisan db:seed --class=DiagnosisDomainSeeder
php artisan serve --host=0.0.0.0 --port=8000
```

For scheduled alerts (weather, tasks, outbreaks):

```bash
php artisan schedule:work
php artisan queue:work database --queue=diagnosis,evaluation,default --tries=3 --timeout=3700
```

## Environment setup (secrets)

Copy `.env.example` → `.env`. **Never commit** `.env` or service-account JSON.

| Variable | Purpose |
|----------|---------|
| `APP_KEY` | Laravel encryption key |
| `DB_*` | Database |
| `GROQ_API_KEY` | Crop vision and voice transcription ([console.groq.com](https://console.groq.com/keys)) |
| `PLANTNET_API_KEY` | Optional plant ID assist |
| `FCM_PROJECT_ID` | Firebase project id |
| `FCM_CREDENTIALS_PATH` | Path to Firebase service account JSON (gitignored) |
| `MARKETEYE_API_KEY` | Crowd-verified market prices (backend only) |
| `MARKETEYE_BASE_URL` | Default `https://marketeye.ahzcode.sbs/api/v1/public` |
| `MAIL_*` | Welcome / password-reset email |

Diagnosis hardening documentation: [`docs/architecture.md`](docs/architecture.md), [`docs/evaluation.md`](docs/evaluation.md), and [`docs/dataset-protocol.md`](docs/dataset-protocol.md).

## Tests

```bash
php artisan test
```

Focused suites: outbreak distance, auth API, scan history ownership.

## Security and privacy foundation

- API tokens expire after 30 days and password changes revoke every token.
- Native clients use bearer tokens. Cross-origin browser API access is denied by default; the future staff dashboard must remain same-origin.
- Scan images and voice clips are strictly decoded, checked by magic bytes/MIME and size limits, and temporary provider files are always removed.
- Outbreak heatmaps expose only 0.05-degree grid cells with at least three distinct farmers. Precise report coordinates remain internal and are never included in outbreak notifications.
- Only canonical diseased scans that are auto-verified or expert-verified can alter field health or contribute to outbreak aggregates. Legacy and disputed scans remain ineligible.
- Terms and privacy metadata is available at `GET /api/legal`; public pages are `/legal/terms` and `/legal/privacy`.
- Registration requires `termsVersion` and `privacyVersion`. `researchConsent` is a separate optional boolean. Existing users receive HTTP 428 with `consentRequired: true` until `POST /api/auth/consent` records current versions.
- Personal-data controls: `GET /api/privacy/export`, individual `DELETE /api/farm/scan-history/{id}`, `DELETE /api/advisor/history`, and password-confirmed `DELETE /api/auth/account`.
- Retention is versioned in `config/security.php`: exports/temp media/OTPs, sync action logs, advisor conversations, and notifications are purged by `agroaide:purge-expired-personal-data`.

AI preferences are persisted through `PUT /api/auth/profile` as `aiResponseDepth` (`concise|balanced|deep`) and `aiRiskTolerance` (`cautious|balanced|bold`).

## Useful artisan commands

```bash
php artisan agroaide:detect-outbreaks
php artisan agroaide:send-weather-alerts
php artisan agroaide:send-task-reminders
php artisan agroaide:test-outbreak-notification --email=you@example.com
php artisan agroaide:purge-expired-personal-data
php artisan agroaide:staff-account
php artisan agroaide:evaluation:import --help
php artisan agroaide:evaluation:run --help
php artisan agroaide:health-snapshot
```

The staff dashboard is served same-origin at `/staff/login` using the local Vite/Tailwind build. Agronomists review scans and read aggregate evaluation metrics; administrator-only pages manage run queueing, confidence-policy activation, staff roles, and audit details. Staff credentials are created interactively; no credentials are seeded or sourced from environment variables. In production, Supervisor runs the database-backed diagnosis/evaluation worker and Laravel scheduler.

Create the first administrator interactively:

```bash
php artisan migrate
php artisan agroaide:staff-account your-email@example.com --role=admin
```

The command securely asks for the administrator's name and hidden password. Then sign in at `http://127.0.0.1:8000/staff/login`, or replace `127.0.0.1` with the backend server/LAN address.

`POST /api/farm/scans/{scan}/feedback` is throttled and idempotent per farmer/scan: repeated taps update the current feedback record instead of creating duplicate metric events.

## API health

`GET /api/health` → `{ "ok": true }`

The backend CI workflow runs the complete test suite, Pint, Composer's advisory audit, and the staff dashboard production build on every push and pull request.
