#!/bin/sh
# Punto de entrada del contenedor Docker del backend.
# Se ejecuta cada vez que el contenedor arranca, antes de levantar Laravel.
# Orden: esperar DB → generar .env → generar APP_KEY → migraciones → carpetas → scheduler → Reverb → servidor HTTP
set -e

# ── 1. ESPERAR A POSTGRESQL ───────────────────────────────────────────────────
# El contenedor de la app arranca antes que la base de datos.
# pg_isready hace ping cada 1 segundo hasta que PostgreSQL acepta conexiones.
# Sin esto, Laravel crashearía al intentar conectarse a una DB que aún no levantó.
DB_HOST_VALUE="${DB_HOST:-database-1.ckzeuqplprxe.us-east-1.rds.amazonaws.com}"
DB_PORT_VALUE="${DB_PORT:-5432}"
DB_USERNAME_VALUE="${DB_USERNAME:-postgres}"

echo "Esperando a que PostgreSQL esté disponible en ${DB_HOST_VALUE}:${DB_PORT_VALUE}..."
until pg_isready -h "$DB_HOST_VALUE" -p "$DB_PORT_VALUE" -U "$DB_USERNAME_VALUE" 2>/dev/null; do
    sleep 1
done
echo "PostgreSQL listo."

# ── 2. GENERAR EL ARCHIVO .env ────────────────────────────────────────────────
# Laravel necesita un archivo .env con todas sus variables de configuración.
# En producción (Kubernetes) no hay .env en la imagen — las variables vienen
# de los Secrets del cluster. Este bloque las toma del entorno del contenedor
# y arma el .env en tiempo de arranque.
# Si ya existe un .env (entorno local con .env.docker) lo usa directamente.
if [ ! -f /app/.env ]; then
    if [ "${APP_ENV:-local}" = "production" ]; then
        cat >/app/.env <<EOF
# ── LARAVEL: configuración base de la app, entorno, clave de cifrado ──────────
APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}
APP_KEY=${APP_KEY:-}
FRONTEND_URL=${FRONTEND_URL:-http://localhost:5173}

# ── POSTGRESQL: base de datos principal (usuarios, reservas, pagos, etc.) ─────
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-database-1.ckzeuqplprxe.us-east-1.rds.amazonaws.com}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-servicios_pro}
DB_USERNAME=${DB_USERNAME:-postgres}
DB_PASSWORD=${DB_PASSWORD:-}

# ── MAIL: envío de emails via Gmail SMTP (confirmaciones, avisos) ─────────────
MAIL_MAILER=${MAIL_MAILER:-smtp}
MAIL_HOST=${MAIL_HOST:-smtp.gmail.com}
MAIL_PORT=${MAIL_PORT:-587}
MAIL_USERNAME=${MAIL_USERNAME:-yoelleyes2013@gmail.com}
MAIL_PASSWORD="${MAIL_PASSWORD:-}"
MAIL_ENCRYPTION=${MAIL_ENCRYPTION:-tls}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-yoelleyes2013@gmail.com}
MAIL_FROM_NAME=${MAIL_FROM_NAME:-AgendaOnline}

# ── REDIS: base de datos en memoria para caché y cola de trabajos ─────────────
REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PORT=${REDIS_PORT:-6379}
REDIS_PASSWORD=${REDIS_PASSWORD:-}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-redis}
CACHE_STORE=${CACHE_STORE:-redis}
SESSION_DRIVER=${SESSION_DRIVER:-database}
VIEW_CACHE_PATH=/app/storage/framework/views
CACHE_PATH=/app/storage/framework/cache

# ── SANCTUM: autenticación por tokens (login → token → peticiones autorizadas) ─
SANCTUM_STATEFUL_DOMAINS=${SANCTUM_STATEFUL_DOMAINS:-}
SANCTUM_TOKEN_EXPIRATION=${SANCTUM_TOKEN_EXPIRATION:-10080}

# ── GOOGLE OAUTH: login con cuenta de Google ──────────────────────────────────
GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID:-}
GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET:-}
GOOGLE_REDIRECT_URI=${GOOGLE_REDIRECT_URI:-}

# ── SERVICIO DE NOTIFICACIONES: microservicio externo del grupo ───────────────
NOTIF_SERVICE_URL=${NOTIF_SERVICE_URL:-}
NOTIF_SERVICE_TOKEN=${NOTIF_SERVICE_TOKEN:-}

# ── PAYPAL: procesador de pagos (sandbox = prueba, live = dinero real) ─────────
PAYPAL_CLIENT_ID=${PAYPAL_CLIENT_ID:-}
PAYPAL_SECRET=${PAYPAL_SECRET:-}
PAYPAL_MODE=${PAYPAL_MODE:-sandbox}

# ── LIVEKIT: videollamadas WebRTC para reservas con modalidad remota ──────────
LIVEKIT_URL=${LIVEKIT_URL:-}
LIVEKIT_API_KEY=${LIVEKIT_API_KEY:-}
LIVEKIT_API_SECRET=${LIVEKIT_API_SECRET:-}

# ── REVERB: servidor WebSocket para notificaciones en tiempo real ─────────────
BROADCAST_DRIVER=reverb
REVERB_APP_ID=${REVERB_APP_ID:-1}
REVERB_APP_KEY=${REVERB_APP_KEY:-}
REVERB_APP_SECRET=${REVERB_APP_SECRET:-}
REVERB_HOST=${REVERB_HOST:-reverb}
REVERB_PORT=${REVERB_PORT:-8080}
REVERB_SCHEME=${REVERB_SCHEME:-http}

# ── ATLAS MONGODB: base de datos NoSQL en la nube, usada solo para logs ───────
ATLAS_LOGS_ENABLED=${ATLAS_LOGS_ENABLED:-false}
ATLAS_MONGODB_URI=${ATLAS_MONGODB_URI:-}
ATLAS_LOGS_DATABASE=${ATLAS_LOGS_DATABASE:-proyecto2026_logs}
ATLAS_LOGS_COLLECTION=${ATLAS_LOGS_COLLECTION:-logs}

# ── CONTROL DEL CONTENEDOR ────────────────────────────────────────────────────
RUN_MIGRATIONS=${RUN_MIGRATIONS:-false}
EOF
    elif [ -f /app/.env.docker ]; then
        # Entorno local con Docker Compose: usa el .env.docker incluido en el repo
        cp /app/.env.docker /app/.env
    fi
fi

# ── 3. GENERAR APP_KEY ────────────────────────────────────────────────────────
# Laravel usa APP_KEY para cifrar cookies, tokens de Sanctum y datos sensibles.
# Si no viene en las variables de entorno (primer deploy), la genera automáticamente.
if [ -z "${APP_KEY:-}" ]; then
    php artisan key:generate --force
fi

# ── 4. MIGRACIONES ────────────────────────────────────────────────────────────
# Crea o actualiza las tablas de la base de datos.
# Solo se ejecuta si RUN_MIGRATIONS=true (controlado desde el Secret de Kubernetes).
# En producción está en false para no correr migraciones automáticas en cada deploy.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

# ── 5. CARPETAS DE ALMACENAMIENTO ─────────────────────────────────────────────
# Laravel necesita estas carpetas con permisos de escritura para funcionar.
# Las crea si no existen (en un contenedor nuevo el filesystem está vacío).
#   logs/         → archivo de errores (laravel.log)
#   cache/        → caché de la aplicación (Redis lo complementa)
#   sessions/     → sesiones de usuario si se usa driver "file"
#   views/        → plantillas Blade compiladas (vistas cacheadas)
mkdir -p /app/storage/logs
mkdir -p /app/storage/framework/cache
mkdir -p /app/storage/framework/sessions
mkdir -p /app/storage/framework/views
touch /app/storage/logs/laravel.log

# Crea el enlace simbólico storage/app/public → public/storage
# Permite acceder a archivos subidos por los usuarios vía URL pública
php artisan storage:link --force

# ── 6. SCHEDULER (segundo plano) ──────────────────────────────────────────────
# Equivalente al cron de Laravel: corre las tareas programadas cada minuto.
# En este proyecto finaliza automáticamente las reservas vencidas cada 5 minutos.
# El & al final lo lanza en segundo plano para que el script siga ejecutando.
php artisan schedule:work &

# ── 7. REVERB — SERVIDOR WEBSOCKET (segundo plano) ───────────────────────────
# Levanta el servidor WebSocket de Laravel Reverb en el puerto 8080.
# Recibe las conexiones de los browsers y les reenvía los eventos en tiempo real
# (notificaciones de reservas, campana, etc.).
# También corre en segundo plano con &.
php artisan reverb:start --host=0.0.0.0 --port=${REVERB_PORT:-8080} --no-interaction &

echo "Backend listo."

# ── 8. SERVIDOR HTTP LARAVEL (primer plano) ───────────────────────────────────
# exec reemplaza este script por el proceso indicado en el CMD del Dockerfile,
# que es: php artisan serve --host=0.0.0.0 --port=8000
# Corre en primer plano — Docker lo usa como proceso principal del contenedor.
# Si este proceso muere, el contenedor se detiene.
exec "$@"
