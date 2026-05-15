<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use App\Models\Reserva;
use App\Models\Resena;
use App\Services\NotificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para el recurso Resena.
 * Un cliente puede dejar una reseña por cada reserva finalizada.
 * Las reseñas son públicas y actualizan el promedio del profesional.
 */
class ResenaController extends Controller
{
    public function __construct(private NotificacionService $notificaciones) {}

    /**
     * GET /api/profesionales/{profesional}/resenas
     * Lista las reseñas visibles de un profesional, paginadas.
     * Opcionalmente filtrable por servicio_id.
     */
    public function index(Profesional $profesional): JsonResponse
    {
        $servicio_id = request()->query('servicio_id');

        $query = Resena::where('profesional_id', $profesional->user_id)
            ->where('visible', true)
            ->with('evaluador:id,name,avatar', 'reserva:id,servicio_id');

        // Si se proporciona servicio_id, filtrar por ese servicio
        if ($servicio_id) {
            $query->whereHas('reserva', function ($q) use ($servicio_id) {
                $q->where('servicio_id', $servicio_id);
            });
        }

        $resenas = $query->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($resenas);
    }

    /**
     * POST /api/reservas/{reserva}/resena
     * El cliente deja una reseña sobre una reserva finalizada.
     * Solo se permite una reseña por reserva.
     */
    public function store(Request $request, Reserva $reserva): JsonResponse
    {
        if ($reserva->cliente_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado para reseñar esta reserva.'], 403);
        }

        if ($reserva->estado !== 'finalizada') {
            return response()->json(['error' => 'Solo se pueden reseñar reservas finalizadas.'], 422);
        }

        if ($reserva->resena) {
            return response()->json(['error' => 'Esta reserva ya tiene una reseña.'], 422);
        }

        $validados = $request->validate([
            'calificacion' => 'required|integer|between:1,5',
            'comentario'   => 'nullable|string|max:1000',
        ]);

        // Obtener el perfil profesional del usuario que ofrece el servicio
        $perfilProfesional = $reserva->profesional?->profesional();

        if (!$perfilProfesional) {
            return response()->json(['error' => 'No se encontró el perfil profesional asociado.'], 422);
        }

        $resena = Resena::create([
            'reserva_id'      => $reserva->id,
            'evaluador_id'    => $request->user()->id,
            'profesional_id'  => $reserva->profesional_id,
            'calificacion'    => $validados['calificacion'],
            'comentario'      => $validados['comentario'] ?? null,
        ]);

        // Recalcular el promedio de calificación del profesional
        $promedio = Resena::where('profesional_id', $reserva->profesional_id)->avg('calificacion');
        $totalResenas = Resena::where('profesional_id', $reserva->profesional_id)->count();

        $perfilProfesional->update([
            'promedio_calificacion' => $promedio,
            'total_calificaciones' => $totalResenas,
        ]);

        $reserva->loadMissing(['cliente', 'profesional', 'servicio']);
        $this->notificaciones->resenaCreada($reserva, $resena);

        return response()->json($resena->load(['evaluador', 'profesional', 'reserva']), 201);
    }

    /**
     * GET /api/mis-resenas
     * Lista las reseñas recibidas por el profesional autenticado (ordenadas por más recientes).
     * Solo accesible por profesionales.
     */
    public function resenasPorProfesional(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verificar que el usuario sea profesional
        if (!$user || $user->rol !== 'profesional') {
            return response()->json(['error' => 'No autorizado. Solo los profesionales pueden ver sus reseñas.'], 403);
        }

        // Obtener todas las reseñas del profesional (paginadas)
        $resenas = Resena::where('profesional_id', $user->id)
            ->with([
                'evaluador:id,name,avatar',
                'reserva:id,servicio_id,cliente_id,fecha_hora',
                'reserva.servicio:id,nombre',
            ])
            ->orderByDesc('created_at')
            ->paginate(15);

        // Calcular estadísticas
        $totalResenas = Resena::where('profesional_id', $user->id)->count();
        $promedio = Resena::where('profesional_id', $user->id)->avg('calificacion');

        // Contar distribución de calificaciones
        $distribucion = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribucion[$i] = Resena::where('profesional_id', $user->id)
                ->where('calificacion', $i)
                ->count();
        }

        return response()->json([
            'data' => $resenas->items(),
            'pagination' => [
                'current_page' => $resenas->currentPage(),
                'last_page' => $resenas->lastPage(),
                'per_page' => $resenas->perPage(),
                'total' => $resenas->total(),
            ],
            'estadisticas' => [
                'total_resenas' => $totalResenas,
                'promedio_calificacion' => round($promedio, 2),
                'distribucion' => $distribucion,
            ],
        ]);
    }
}
