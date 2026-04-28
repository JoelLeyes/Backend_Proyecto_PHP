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

if [ ! -f /app/.env ] && [ -f /app/.env.docker ]; then
    cp /app/.env.docker /app/.env
fi

php artisan key:generate --force
php artisan migrate --force
php artisan storage:link --force

echo "Backend listo."
exec "$@"
