<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $totalReservas    = Reserva::count();
        $canceladas       = Reserva::where('estado', 'cancelada')->count();
        $finalizadas      = Reserva::where('estado', 'finalizada')->count();

        return response()->json([
            'usuarios' => [
                'total'         => User::count(),
                'clientes'      => User::where('rol', 'cliente')->count(),
                'profesionales' => User::where('rol', 'profesional')->count(),
                'admins'        => User::where('rol', 'admin')->count(),
                'inactivos'     => User::where('activo', false)->count(),
            ],
            'reservas' => [
                'total'             => $totalReservas,
                'pendientes'        => Reserva::where('estado', 'pendiente')->count(),
                'confirmadas'       => Reserva::where('estado', 'confirmada')->count(),
                'finalizadas'       => $finalizadas,
                'canceladas'        => $canceladas,
                'tasa_cancelacion'  => $totalReservas > 0 ? round($canceladas / $totalReservas * 100, 1) : 0,
                'tasa_finalizacion' => $totalReservas > 0 ? round($finalizadas / $totalReservas * 100, 1) : 0,
            ],
            'profesionales' => [
                'activos'   => Profesional::where('activo', true)->count(),
                'inactivos' => Profesional::where('activo', false)->count(),
            ],
            'recientes' => [
                'reservas_hoy'  => Reserva::whereDate('created_at', today())->count(),
                'usuarios_semana' => User::where('created_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
    }

    /**
     * GET /api/admin/usuarios
     */
    public function usuarios(Request $request): JsonResponse
    {
        $usuarios = User::query()
            ->when($request->rol, fn($q) => $q->where('rol', $request->rol))
            ->when($request->filled('activo'), fn($q) => $q->where('activo', $request->boolean('activo')))
            ->when($request->busqueda, fn($q) => $q
                ->where(fn($s) => $s
                    ->where('name',  'ilike', "%{$request->busqueda}%")
                    ->orWhere('email', 'ilike', "%{$request->busqueda}%")))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($usuarios);
    }

    /**
     * PUT /api/admin/usuarios/{usuario}
     * Edita nombre, email, teléfono, rol y estado de un usuario.
     */
    public function actualizarUsuario(Request $request, User $usuario): JsonResponse
    {
        $validados = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => ['sometimes', 'email', Rule::unique('users')->ignore($usuario->id)],
            'telefono' => 'nullable|string|max:20',
            'rol'      => ['sometimes', Rule::in(['cliente', 'profesional', 'admin'])],
            'activo'   => 'sometimes|boolean',
        ]);

        // No puede quitarse el rol admin a sí mismo
        if (isset($validados['rol']) && $usuario->id === $request->user()->id && $validados['rol'] !== 'admin') {
            return response()->json(['error' => 'No podés cambiar tu propio rol de administrador.'], 422);
        }

        // Si pasa de cualquier rol a profesional, crear perfil si no existe
        if (isset($validados['rol']) && $validados['rol'] === 'profesional' && !$usuario->profesional) {
            Profesional::create(['user_id' => $usuario->id]);
        }

        $usuario->update($validados);

        return response()->json([
            'usuario' => $usuario->fresh()->load('profesional'),
            'mensaje' => 'Usuario actualizado correctamente.',
        ]);
    }

    /**
     * PATCH /api/admin/usuarios/{usuario}/activar
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

    /**
     * GET /api/admin/reservas
     * Lista todas las reservas del sistema con filtros.
     */
    public function reservas(Request $request): JsonResponse
    {
        $reservas = Reserva::with(['servicio', 'cliente', 'profesional'])
            ->when($request->filled('estado'), fn($q) => $q->where('estado', $request->estado))
            ->when($request->filled('desde'),  fn($q) => $q->whereDate('fecha_hora', '>=', $request->desde))
            ->when($request->filled('hasta'),  fn($q) => $q->whereDate('fecha_hora', '<=', $request->hasta))
            ->when($request->filled('busqueda'), fn($q) => $q
                ->whereHas('cliente',      fn($u) => $u->where('name', 'ilike', "%{$request->busqueda}%"))
                ->orWhereHas('profesional', fn($u) => $u->where('name', 'ilike', "%{$request->busqueda}%"))
                ->orWhereHas('servicio',   fn($s) => $s->where('nombre', 'ilike', "%{$request->busqueda}%")))
            ->orderByDesc('fecha_hora')
            ->paginate(25);

        return response()->json($reservas);
    }
}
