<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use App\Models\Servicio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para el recurso Servicio.
 * Los servicios se listan públicamente, pero solo el profesional
 * dueño puede crear, editar o desactivarlos.
 */
class ServicioController extends Controller
{
    /**
     * GET /api/profesionales/{profesional}/servicios
     * Lista los servicios activos de un profesional con sus paquetes.
     */
    public function index(Profesional $profesional): JsonResponse
    {
        $servicios = $profesional->servicios()
            ->where('activo', true)
            ->with('paquetes')
            ->get();

        return response()->json($servicios);
    }

    /**
     * POST /api/profesionales/{profesional}/servicios
     * Crea un nuevo servicio para el profesional autenticado.
     */
    public function store(Request $request, Profesional $profesional): JsonResponse
    {
        $this->authorize('manage', $profesional);

        $validados = $request->validate([
            'nombre'           => 'required|string|max:255',
            'descripcion'      => 'nullable|string',
            'precio'           => 'required|numeric|min:0',
            'duracion_minutos' => 'required|integer|min:15|max:480',
            'modalidad'        => 'required|in:presencial,remota,hibrida',
        ]);

        $servicio = $profesional->servicios()->create($validados);

        return response()->json($servicio, 201);
    }

    /**
     * GET /api/profesionales/{profesional}/servicios/{servicio}
     * Muestra un servicio con sus paquetes disponibles.
     */
    public function show(Profesional $profesional, Servicio $servicio): JsonResponse
    {
        $servicio->load('paquetes');

        return response()->json($servicio);
    }

    /**
     * PUT /api/profesionales/{profesional}/servicios/{servicio}
     * Actualiza los datos de un servicio existente.
     */
    public function update(Request $request, Profesional $profesional, Servicio $servicio): JsonResponse
    {
        $this->authorize('manage', $profesional);

        $validados = $request->validate([
            'nombre'           => 'sometimes|string|max:255',
            'descripcion'      => 'nullable|string',
            'precio'           => 'sometimes|numeric|min:0',
            'duracion_minutos' => 'sometimes|integer|min:15|max:480',
            'modalidad'        => 'sometimes|in:presencial,remota,hibrida',
            'activo'           => 'sometimes|boolean',
        ]);

        $servicio->update($validados);

        return response()->json($servicio);
    }

    /**
     * DELETE /api/profesionales/{profesional}/servicios/{servicio}
     * Desactiva un servicio (no se elimina para preservar el historial de reservas).
     */
    public function destroy(Profesional $profesional, Servicio $servicio): JsonResponse
    {
        $this->authorize('manage', $profesional);

        $servicio->update(['activo' => false]);

        return response()->json(['mensaje' => 'Servicio desactivado correctamente.']);
    }
}
