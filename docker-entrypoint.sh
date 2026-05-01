#!/bin/sh
set -e

DB_HOST_VALUE="${DB_HOST:-postgres}"
DB_PORT_VALUE="${DB_PORT:-5432}"
DB_USERNAME_VALUE="${DB_USERNAME:-postgres}"

echo "Esperando a que PostgreSQL esté disponible en ${DB_HOST_VALUE}:${DB_PORT_VALUE}..."
until pg_isready -h "$DB_HOST_VALUE" -p "$DB_PORT_VALUE" -U "$DB_USERNAME_VALUE" 2>/dev/null; do
    sleep 1
done
echo "PostgreSQL listo."

if [ ! -f /app/.env ]; then
    if [ "${APP_ENV:-local}" = "production" ]; then
        cat >/app/.env <<EOF
APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-database-1.cexnzzrh862s.us-east-1.rds.amazonaws.com}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-servicios_pro}
DB_USERNAME=${DB_USERNAME:-postgres}
DB_PASSWORD=${DB_PASSWORD:-}
REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PORT=${REDIS_PORT:-6379}
REDIS_PASSWORD=${REDIS_PASSWORD:-}
EOF
    elif [ -f /app/.env.docker ]; then
        cp /app/.env.docker /app/.env
    fi
fi

php artisan key:generate --force
php artisan migrate --force
php artisan storage:link --force

echo "Backend listo."
exec "$@"
