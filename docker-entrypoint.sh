#!/bin/sh
set -e

DB_HOST_VALUE="${DB_HOST:-database-1.ckzeuqplprxe.us-east-1.rds.amazonaws.com}"
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
APP_KEY=${APP_KEY:-}
FRONTEND_URL=${FRONTEND_URL:-http://localhost:5173}
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-database-1.ckzeuqplprxe.us-east-1.rds.amazonaws.com}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-servicios_pro}
DB_USERNAME=${DB_USERNAME:-postgres}
DB_PASSWORD=${DB_PASSWORD:-}
MAIL_MAILER=${MAIL_MAILER:-smtp}
MAIL_HOST=${MAIL_HOST:-smtp.gmail.com}
MAIL_PORT=${MAIL_PORT:-587}
MAIL_USERNAME=${MAIL_USERNAME:-yoelleyes2013@gmail.com}
MAIL_PASSWORD="${MAIL_PASSWORD:-}"
MAIL_ENCRYPTION=${MAIL_ENCRYPTION:-tls}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-yoelleyes2013@gmail.com}
MAIL_FROM_NAME=${MAIL_FROM_NAME:-AgendaOnline}
REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PORT=${REDIS_PORT:-6379}
REDIS_PASSWORD=${REDIS_PASSWORD:-}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-redis}
CACHE_STORE=${CACHE_STORE:-redis}
SESSION_DRIVER=${SESSION_DRIVER:-database}
VIEW_CACHE_PATH=/app/storage/framework/views
CACHE_PATH=/app/storage/framework/cache
SANCTUM_STATEFUL_DOMAINS=${SANCTUM_STATEFUL_DOMAINS:-}
SANCTUM_TOKEN_EXPIRATION=${SANCTUM_TOKEN_EXPIRATION:-10080}
GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID:-}
GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET:-}
GOOGLE_REDIRECT_URI=${GOOGLE_REDIRECT_URI:-}
NOTIF_SERVICE_URL=${NOTIF_SERVICE_URL:-}
NOTIF_SERVICE_TOKEN=${NOTIF_SERVICE_TOKEN:-}
PAYPAL_CLIENT_ID=${PAYPAL_CLIENT_ID:-}
PAYPAL_SECRET=${PAYPAL_SECRET:-}
PAYPAL_MODE=${PAYPAL_MODE:-sandbox}
LIVEKIT_URL=${LIVEKIT_URL:-}
LIVEKIT_API_KEY=${LIVEKIT_API_KEY:-}
LIVEKIT_API_SECRET=${LIVEKIT_API_SECRET:-}
BROADCAST_DRIVER=reverb
REVERB_APP_ID=${REVERB_APP_ID:-1}
REVERB_APP_KEY=${REVERB_APP_KEY:-}
REVERB_APP_SECRET=${REVERB_APP_SECRET:-}
REVERB_HOST=0.0.0.0
REVERB_PORT=${REVERB_PORT:-8080}
REVERB_SCHEME=${REVERB_SCHEME:-https}
ATLAS_LOGS_ENABLED=${ATLAS_LOGS_ENABLED:-false}
ATLAS_MONGODB_URI=${ATLAS_MONGODB_URI:-}
ATLAS_LOGS_DATABASE=${ATLAS_LOGS_DATABASE:-proyecto2026_logs}
ATLAS_LOGS_COLLECTION=${ATLAS_LOGS_COLLECTION:-logs}
RUN_MIGRATIONS=${RUN_MIGRATIONS:-false}
EOF
    elif [ -f /app/.env.docker ]; then
        cp /app/.env.docker /app/.env
    fi
fi

if [ -z "${APP_KEY:-}" ]; then
    php artisan key:generate --force
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

# Ensure Laravel writable paths exist before serving requests.
mkdir -p /app/storage/logs
mkdir -p /app/storage/framework/cache
mkdir -p /app/storage/framework/sessions
mkdir -p /app/storage/framework/views
touch /app/storage/logs/laravel.log

php artisan storage:link --force

# Scheduler en background: finaliza reservas vencidas cada 5 minutos
php artisan schedule:work &

# Reverb WebSocket server en background
php artisan reverb:start --host=0.0.0.0 --port=${REVERB_PORT:-8080} --no-interaction &

echo "Backend listo."
exec "$@"
