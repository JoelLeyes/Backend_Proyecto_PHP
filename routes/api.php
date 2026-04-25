<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DisponibilidadController;
use App\Http\Controllers\Api\PaqueteController;
use App\Http\Controllers\Api\ProfesionalController;
use App\Http\Controllers\Api\ResenaController;
use App\Http\Controllers\Api\ReservaController;
use App\Http\Controllers\Api\ServicioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API — Plataforma de Servicios Profesionales
|--------------------------------------------------------------------------
|
| Las rutas públicas no requieren autenticación (exploración de la plataforma).
| Las rutas protegidas requieren el token Bearer de Sanctum.
|
| Prefijo base: /api  (configurado en bootstrap/app.php)
|
*/

// ─── Autenticación ─────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('registrar', [AuthController::class, 'registrar']);
    Route::post('iniciar-sesion', [AuthController::class, 'iniciarSesion']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('cerrar-sesion', [AuthController::class, 'cerrarSesion']);
        Route::get('perfil', [AuthController::class, 'perfil']);
    });
});

// ─── Profesionales (público) ────────────────────────────────────────────────
//
// GET /api/profesionales             -> lista con filtros
// GET /api/profesionales/{id}        -> perfil completo
//
Route::get('profesionales', [ProfesionalController::class, 'index']);
Route::get('profesionales/{profesional}', [ProfesionalController::class, 'show']);

// ─── Reseñas públicas de un profesional ────────────────────────────────────
Route::get('profesionales/{profesional}/resenas', [ResenaController::class, 'index']);

// ─── Servicios de un profesional (público) ─────────────────────────────────
Route::get('profesionales/{profesional}/servicios', [ServicioController::class, 'index']);
Route::get('profesionales/{profesional}/servicios/{servicio}', [ServicioController::class, 'show']);

// ─── Horarios disponibles (público) ────────────────────────────────────────
Route::get(
    'profesionales/{profesional}/disponibilidad/horarios',
    [DisponibilidadController::class, 'horarios']
);

// ─── Paquetes de un servicio (público) ─────────────────────────────────────
Route::get(
    'profesionales/{profesional}/servicios/{servicio}/paquetes',
    [PaqueteController::class, 'index']
);

// ─── Rutas protegidas (requieren token de Sanctum) ─────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Perfil del profesional (solo el dueño puede editar)
    Route::put('profesionales/{profesional}', [ProfesionalController::class, 'update']);

    // Reglas de disponibilidad
    Route::get('profesionales/{profesional}/disponibilidad/reglas', [DisponibilidadController::class, 'reglas']);
    Route::post('profesionales/{profesional}/disponibilidad/reglas', [DisponibilidadController::class, 'guardarRegla']);
    Route::put('profesionales/{profesional}/disponibilidad/reglas/{regla}', [DisponibilidadController::class, 'actualizarRegla']);
    Route::delete('profesionales/{profesional}/disponibilidad/reglas/{regla}', [DisponibilidadController::class, 'eliminarRegla']);

    // Excepciones de disponibilidad (feriados, licencias, etc.)
    Route::get('profesionales/{profesional}/disponibilidad/excepciones', [DisponibilidadController::class, 'excepciones']);
    Route::post('profesionales/{profesional}/disponibilidad/excepciones', [DisponibilidadController::class, 'guardarExcepcion']);

    // Servicios (gestión por el profesional)
    Route::post('profesionales/{profesional}/servicios', [ServicioController::class, 'store']);
    Route::put('profesionales/{profesional}/servicios/{servicio}', [ServicioController::class, 'update']);
    Route::delete('profesionales/{profesional}/servicios/{servicio}', [ServicioController::class, 'destroy']);

    // Paquetes de servicio (gestión por el profesional)
    Route::post('profesionales/{profesional}/servicios/{servicio}/paquetes', [PaqueteController::class, 'store']);
    Route::delete('profesionales/{profesional}/servicios/{servicio}/paquetes/{paquete}', [PaqueteController::class, 'destroy']);

    // Reservas
    Route::get('reservas', [ReservaController::class, 'index']);
    Route::post('reservas', [ReservaController::class, 'store']);
    Route::get('reservas/{reserva}', [ReservaController::class, 'show']);
    Route::post('reservas/{reserva}/confirmar', [ReservaController::class, 'confirmar']);
    Route::post('reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar']);
    Route::patch('reservas/{reserva}/reprogramar', [ReservaController::class, 'reprogramar']);

    // Reseñas (solo el cliente de la reserva puede crearla)
    Route::post('reservas/{reserva}/resena', [ResenaController::class, 'store']);

    // Paquetes del cliente
    Route::get('mis-paquetes', [PaqueteController::class, 'misPaquetes']);
    Route::post('paquetes-servicio/{paqueteServicio}/comprar', [PaqueteController::class, 'comprar']);

    // Panel administrativo (solo rol admin)
    Route::middleware('can:admin')->prefix('admin')->group(function () {
        Route::get('estadisticas', [AdminController::class, 'estadisticas']);
        Route::get('usuarios', [AdminController::class, 'usuarios']);
        Route::patch('usuarios/{usuario}/activar', [AdminController::class, 'activarDesactivar']);
    });
});
