#!/usr/bin/env bash
set -euo pipefail

APP_HOME="${APP_HOME:-/var/www/html}"
cd "$APP_HOME"

echo "[entrypoint] AgroAide backend starting…"

# ---------------------------------------------------------------------------
# 1. Wait for database (poll, don't fixed-sleep)
# ---------------------------------------------------------------------------
wait_for_database() {
  local connection="${DB_CONNECTION:-mysql}"
  if [[ "$connection" == "sqlite" ]]; then
    echo "[entrypoint] DB_CONNECTION=sqlite — skipping network wait."
    return 0
  fi

  local host="${DB_HOST:-127.0.0.1}"
  local port="${DB_PORT:-3306}"
  local database="${DB_DATABASE:-agroaide}"
  local username="${DB_USERNAME:-root}"
  local password="${DB_PASSWORD:-}"
  local max_attempts="${DB_WAIT_ATTEMPTS:-60}"
  local sleep_seconds="${DB_WAIT_SLEEP:-2}"

  echo "[entrypoint] Waiting for database ${username}@${host}:${port}/${database} (${connection})…"

  local attempt=1
  while (( attempt <= max_attempts )); do
    if php -r "
      \$host = getenv('DB_HOST') ?: '127.0.0.1';
      \$port = getenv('DB_PORT') ?: '3306';
      \$db   = getenv('DB_DATABASE') ?: 'agroaide';
      \$user = getenv('DB_USERNAME') ?: 'root';
      \$pass = getenv('DB_PASSWORD') ?: '';
      \$driver = getenv('DB_CONNECTION') ?: 'mysql';
      try {
        if (\$driver === 'pgsql') {
          new PDO(\"pgsql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass, [PDO::ATTR_TIMEOUT => 3]);
        } else {
          new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass, [PDO::ATTR_TIMEOUT => 3]);
        }
        exit(0);
      } catch (Throwable \$e) {
        fwrite(STDERR, \$e->getMessage() . PHP_EOL);
        exit(1);
      }
    " >/tmp/db-wait.log 2>&1; then
      echo "[entrypoint] Database is ready (attempt ${attempt})."
      return 0
    fi

    echo "[entrypoint] Database not ready yet (attempt ${attempt}/${max_attempts})…"
    sleep "$sleep_seconds"
    ((attempt++))
  done

  echo "[entrypoint] ERROR: Database never became reachable after ${max_attempts} attempts." >&2
  cat /tmp/db-wait.log >&2 || true
  exit 1
}

wait_for_database

# Ensure runtime permissions (Coolify volume mounts can reset ownership)
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

# ---------------------------------------------------------------------------
# 2. Migrate (non-interactive)
# ---------------------------------------------------------------------------
echo "[entrypoint] Running migrations…"
php artisan migrate --force

# ---------------------------------------------------------------------------
# 3. storage:link if missing
# ---------------------------------------------------------------------------
if [[ ! -L public/storage ]]; then
  echo "[entrypoint] Creating storage symlink…"
  php artisan storage:link || true
else
  echo "[entrypoint] storage symlink already present."
fi

# ---------------------------------------------------------------------------
# 4. Cache config / routes / views
# ---------------------------------------------------------------------------
echo "[entrypoint] Caching config, routes, views…"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------------
# 5. Seeders — SKIPPED
# DatabaseSeeder creates a fixed Test User (test@example.com) and is NOT
# idempotent (second run fails on unique email). Do not auto-seed in prod.
# Run manually only if you intentionally want that demo user:
#   php artisan db:seed --force
# ---------------------------------------------------------------------------
echo "[entrypoint] Skipping db:seed (DatabaseSeeder is not idempotent)."

# ---------------------------------------------------------------------------
# 6. Hand off to supervisord (nginx + php-fpm + schedule:work)
# ---------------------------------------------------------------------------
echo "[entrypoint] Starting supervisord…"
exec "$@"
