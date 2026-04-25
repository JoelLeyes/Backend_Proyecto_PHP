<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use App\Models\Reserva;
use App\Models\Resena;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para el recurso Resena.
 * Un cliente puede dejar una reseña por cada reserva finalizada.
 * Las reseñas son públicas y actualizan el promedio del profesional.
 */
class ResenaController extends Controller
{
    /**
     * GET /api/profesionales/{profesional}/resenas
     * Lista las reseñas visibles de un profesional, paginadas.
     */
    public function index(Profesional $profesional): JsonResponse
    {
        $resenas = Resena::where('profesional_id', $profesional->user_id)
            ->where('visible', true)
            ->with('evaluador:id,name,avatar')
            ->orderByDesc('created_at')
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

        $resena = Resena::create([
            'reserva_id'      => $reserva->id,
            'evaluador_id'    => $request->user()->id,
            'profesional_id'  => $reserva->profesional_id,
            'calificacion'    => $validados['calificacion'],
            'comentario'      => $validados['comentario'] ?? null,
        ]);

        // Recalcular el promedio de calificación del profesional
        $promedio       = Resena::where('profesional_id', $reserva->profesional_id)->avg('calificacion');
        $totalResenas   = Resena::where('profesional_id', $reserva->profesional_id)->count();

        $profesional = $reserva->profesional->profesional;
        $profesional->update([
            'promedio_calificacion' => $promedio,
            'total_calificaciones'  => $totalResenas,
        ]);

        return response()->json($resena, 201);
    }
}
