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
 */
class AdminController extends Controller
{
    /**
     * GET /api/admin/estadisticas
     */
    public function estadisticas(): JsonResponse
    {
        return response()->json([
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
        ]);
    }

    /**
     * GET /api/admin/usuarios
     * Lista usuarios con filtros opcionales.
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
     * Activa o desactiva un usuario. No permite actuar sobre el propio admin.
     */
    public function activarDesactivar(Request $request, User $usuario): JsonResponse
    {
        if ($usuario->id === $request->user()->id) {
            return response()->json(['error' => 'No podés desactivar tu propia cuenta.'], 422);
        }

        $usuario->update(['activo' => !$usuario->activo]);

        $estado = $usuario->activo ? 'activado' : 'desactivado';

        return response()->json([
            'usuario' => $usuario,
            'mensaje' => "Usuario {$estado} correctamente.",
        ]);
    }
}
