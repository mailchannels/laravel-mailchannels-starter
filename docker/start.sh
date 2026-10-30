#!/bin/sh
# Container entrypoint: prepare SQLite, migrate, serve on $PORT.
set -e
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi
DB_FILE="${DB_DATABASE:-/app/database/database.sqlite}"
[ -f "$DB_FILE" ] || touch "$DB_FILE"
php artisan config:cache
php artisan migrate --force
exec php artisan serve --host=0.0.0.0 --port="${PORT:-3006}" --no-reload
