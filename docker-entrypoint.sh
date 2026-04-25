#!/bin/bash
set -e

echo "Esperando a que PostgreSQL esté disponible..."
until pg_isready -h postgres -p 5432 -U postgres 2>/dev/null; do
    sleep 1
done
echo "PostgreSQL listo."

cp /app/.env.docker /app/.env

php artisan key:generate --force
php artisan migrate --force
php artisan storage:link --force

echo "Backend listo."
exec "$@"
