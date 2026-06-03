#!/usr/bin/env pwsh
# ============================================================
# Setup WebSockets - Backend (Laravel Reverb)
# Ejecutar desde: Backend_Proyecto_PHP/
# ============================================================

Write-Host "🚀 Configurando WebSockets - Backend" -ForegroundColor Green

# 1. Instalar composer
Write-Host "`n1️⃣  Instalando dependencias de PHP..." -ForegroundColor Yellow
docker run --rm -v "${PWD}:/app" -w /app composer:2 install

# 2. Copiar .env.example a .env
Write-Host "`n2️⃣  Configurando variables de entorno..." -ForegroundColor Yellow
if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "✓ Archivo .env creado" -ForegroundColor Green
} else {
    Write-Host "✓ Archivo .env ya existe" -ForegroundColor Green
}

# 3. Generar app key
Write-Host "`n3️⃣  Generando APP_KEY..." -ForegroundColor Yellow
docker compose run --rm app php artisan key:generate

# 4. Ejecutar migraciones
Write-Host "`n4️⃣  Ejecutando migraciones..." -ForegroundColor Yellow
docker compose run --rm app php artisan migrate --force

# 5. Iniciar servicios
Write-Host "`n5️⃣  Iniciando Docker Compose..." -ForegroundColor Yellow
Write-Host "`n✅ Servicios que se van a iniciar:" -ForegroundColor Green
Write-Host "   - app (API Laravel) en puerto 8000" -ForegroundColor Cyan
Write-Host "   - reverb (WebSocket) en puerto 8080" -ForegroundColor Cyan
Write-Host "   - postgres (Base de datos) en puerto 5432" -ForegroundColor Cyan
Write-Host "   - redis (Cache) en puerto 6379" -ForegroundColor Cyan

docker compose up --build

# ============================================================
# Luego ejecutar setup_frontend.ps1 en Frontend_Proyecto_PHP/
# ============================================================
