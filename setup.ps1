# setup.ps1
# Bootstraps the Backend Laravel project using Docker.
# Run this ONCE after cloning the repo.
#
# Requisito: Docker Desktop corriendo.
# Uso: .\setup.ps1

$ErrorActionPreference = "Stop"
$ProjectDir = $PSScriptRoot

Write-Host ""
Write-Host "== Backend Servicios Pro — Setup ==" -ForegroundColor Cyan

# Check Docker
try {
    docker info | Out-Null
} catch {
    Write-Host "ERROR: Docker no esta corriendo. Inicia Docker Desktop primero." -ForegroundColor Red
    exit 1
}

# If composer.json doesn't exist, create the Laravel project
if (-not (Test-Path "$ProjectDir\composer.json")) {
    Write-Host "Creando proyecto Laravel 11..." -ForegroundColor Yellow
    docker run --rm `
        -v "${ProjectDir}:/app" `
        -w /app `
        composer:2 create-project laravel/laravel . --prefer-dist --no-interaction
    Write-Host "Laravel creado." -ForegroundColor Green
} else {
    Write-Host "composer.json ya existe, saltando create-project." -ForegroundColor Gray
}

# Install extra packages
Write-Host "Instalando paquetes adicionales..." -ForegroundColor Yellow
docker run --rm `
    -v "${ProjectDir}:/app" `
    -w /app `
    composer:2 require predis/predis --no-interaction

Write-Host ""
Write-Host "Setup completo. Ahora ejecuta:" -ForegroundColor Green
Write-Host "  docker compose up --build" -ForegroundColor White
