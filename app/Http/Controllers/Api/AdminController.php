<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel administrativo.
 * Solo accesible para usuarios con rol "admin".
 * Permite monitorear estadísticas y gestionar usuarios.
 */
class AdminController extends Controller
{
    /**
     * GET /api/admin/estadisticas
     * Retorna métricas generales del sistema: usuarios, reservas y profesionales.
     */
    public function estadisticas(): JsonResponse
    {
        $datos = [
            'usuarios' => [
                'total'         => User::count(),
                'clientes'      => User::where('rol', 'cliente')->count(),
                'profesionales' => User::where('rol', 'profesional')->count(),
            ],
            'reservas' => [
                'total'      => Reserva::count(),
                'pendientes' => Reserva::where('estado', 'pendiente')->count(),
                'confirmadas' => Reserva::where('estado', 'confirmada')->count(),
                'finalizadas' => Reserva::where('estado', 'finalizada')->count(),
                'canceladas'  => Reserva::where('estado', 'cancelada')->count(),
            ],
            'profesionales' => [
                'activos'   => Profesional::where('activo', true)->count(),
                'inactivos' => Profesional::where('activo', false)->count(),
            ],
        ];

        return response()->json($datos);
    }

    /**
     * GET /api/admin/usuarios
     * Lista todos los usuarios con filtros opcionales por rol y búsqueda.
     */
    public function usuarios(Request $request): JsonResponse
    {
        $usuarios = User::query()
            ->when($request->rol, fn($q) => $q->where('rol', $request->rol))
            ->when($request->busqueda, fn($q) => $q
                ->where('name', 'ilike', "%{$request->busqueda}%")
                ->orWhere('email', 'ilike', "%{$request->busqueda}%"))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($usuarios);
    }

    /**
     * PATCH /api/admin/usuarios/{usuario}/activar
     * Activa o desactiva un usuario (funcionalidad a implementar con campo activo).
     */
    public function activarDesactivar(User $usuario): JsonResponse
    {
        return response()->json(['mensaje' => 'Funcionalidad pendiente de implementar.'], 501);
    }
}
