<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para el recurso Profesional.
 * Permite buscar y filtrar profesionales públicamente,
 * y que cada profesional edite su propio perfil.
 */
class ProfesionalController extends Controller
{
    /**
     * GET /api/profesionales
     * Lista profesionales activos con filtros opcionales:
     * modalidad, ciudad y búsqueda por nombre/bio.
     */
    public function index(Request $request): JsonResponse
    {
        $consulta = Profesional::with('usuario')
            ->where('activo', true);

        if ($request->filled('modalidad')) {
            $consulta->where('modalidad', $request->modalidad);
        }

        if ($request->filled('ciudad')) {
            $consulta->where('ciudad', 'ilike', '%' . $request->ciudad . '%');
        }

        if ($request->filled('busqueda')) {
            $termino = $request->busqueda;
            $consulta->where(function ($q) use ($termino) {
                $q->where('nombre_negocio', 'ilike', "%$termino%")
                  ->orWhere('bio', 'ilike', "%$termino%")
                  ->orWhereHas('usuario', fn($u) => $u->where('name', 'ilike', "%$termino%"));
            });
        }

        $profesionales = $consulta->orderByDesc('promedio_calificacion')->paginate(15);

        return response()->json($profesionales);
    }

    /**
     * GET /api/profesionales/{profesional}
     * Muestra el perfil completo de un profesional con sus servicios activos.
     */
    public function show(Profesional $profesional): JsonResponse
    {
        $profesional->load(['usuario', 'servicios' => fn($q) => $q->where('activo', true)]);

        return response()->json($profesional);
    }

    /**
     * PUT /api/profesionales/{profesional}
     * Actualiza el perfil de un profesional (solo el dueño puede hacerlo).
     */
    public function update(Request $request, Profesional $profesional): JsonResponse
    {
        $this->authorize('update', $profesional);

        $validados = $request->validate([
            'nombre_negocio'   => 'nullable|string|max:255',
            'bio'              => 'nullable|string',
            'modalidad'        => 'nullable|in:presencial,remota,hibrida',
            'direccion'        => 'nullable|string|max:255',
            'ciudad'           => 'nullable|string|max:100',
            'pais'             => 'nullable|string|max:3',
            'latitud'          => 'nullable|numeric|between:-90,90',
            'longitud'         => 'nullable|numeric|between:-180,180',
            'horas_cancelacion' => 'nullable|integer|min:0|max:168',
        ]);

        $profesional->update($validados);

        return response()->json($profesional);
    }
}
