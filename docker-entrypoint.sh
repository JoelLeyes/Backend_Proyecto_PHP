#!/bin/sh
set -e

DB_HOST_VALUE="${DB_HOST:-database-1.ckzeuqplprxe.us-east-1.rds.amazonaws.com}"
DB_PORT_VALUE="${DB_PORT:-5432}"
DB_USERNAME_VALUE="${DB_USERNAME:-postgres}"
pids=""

cleanup() {
    trap - TERM INT

    for pid in $pids; do
        if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
            kill "$pid" 2>/dev/null || true
        fi
    done
}

trap cleanup TERM INT

echo "Esperando a que PostgreSQL esté disponible en ${DB_HOST_VALUE}:${DB_PORT_VALUE}..."
until pg_isready -h "$DB_HOST_VALUE" -p "$DB_PORT_VALUE" -U "$DB_USERNAME_VALUE" 2>/dev/null; do
    sleep 1
done
echo "PostgreSQL listo."

if [ -f /app/.env.docker ] && [ ! -f /app/.env ]; then
    cp /app/.env.docker /app/.env
fi

if [ -z "${APP_KEY:-}" ]; then
    php artisan key:generate --force
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

mkdir -p /app/storage/logs
mkdir -p /app/storage/framework/cache
mkdir -p /app/storage/framework/sessions
mkdir -p /app/storage/framework/views
touch /app/storage/logs/laravel.log

php artisan storage:link --force

php artisan schedule:work &
pids="$pids $!"
php artisan reverb:start --host=0.0.0.0 --port="${REVERB_PORT:-8080}" --no-interaction &
pids="$pids $!"

echo "Backend listo."
"$@" &
server_pid=$!
pids="$pids $server_pid"

wait "$server_pid"
status=$?
cleanup
exit "$status"
