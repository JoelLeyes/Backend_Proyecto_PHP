<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ubicacion;
use App\Models\Servicio;
use App\Models\Profesional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador API para gestión de ubicaciones.
 * Las ubicaciones se siguen creando por profesional, pero cada servicio
 * usa una ubicación principal mediante la relación ubicacion_id.
 */
class UbicacionController extends Controller
{
    /**
     * GET /api/ubicaciones
     * Lista las ubicaciones del profesional autenticado.
     */
    public function index(): JsonResponse
    {
        $profesional = Auth::user()->profesional;
        if (!$profesional) {
            return response()->json(['error' => 'Usuario no es profesional'], 403);
        }

        $ubicaciones = $profesional->ubicaciones()->get();
        return response()->json($ubicaciones);
    }

    /**
     * POST /api/ubicaciones
     * Crea una nueva ubicación con reverse geocoding opcional.
     * 
     * Body:
     * {
     *   "nombre": "Consultorio 1",
     *   "latitud": -34.603,
     *   "longitud": -58.381,
     *   "direccion": "Av. Corrientes 1234",      // opcional (auto-rellenado)
     *   "ciudad": "Buenos Aires",                 // opcional (auto-rellenado)
     *   "pais": "Argentina"                       // opcional (auto-rellenado)
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $profesional = Auth::user()->profesional;
        if (!$profesional) {
            return response()->json(['error' => 'Usuario no es profesional'], 403);
        }

        $validados = $request->validate([
            'nombre'     => 'required|string|max:255',
            'latitud'    => 'required|numeric|between:-90,90',
            'longitud'   => 'required|numeric|between:-180,180',
            'direccion'  => 'nullable|string|max:255',
            'ciudad'     => 'nullable|string|max:255',
            'pais'       => 'nullable|string|max:255',
        ]);

        // Intentar reverse geocoding si no se proporcionó dirección/ciudad
        if (empty($validados['direccion']) || empty($validados['ciudad'])) {
            $geocoded = $this->reverseGeocode($validados['latitud'], $validados['longitud']);
            if ($geocoded) {
                $validados['direccion'] = $validados['direccion'] ?? $geocoded['direccion'] ?? null;
                $validados['ciudad']    = $validados['ciudad'] ?? $geocoded['ciudad'] ?? null;
                $validados['pais']      = $validados['pais'] ?? $geocoded['pais'] ?? null;
            }
        }

        $ubicacion = $profesional->ubicaciones()->create($validados);

        return response()->json($ubicacion, 201);
    }

    /**
     * PUT /api/ubicaciones/{ubicacion}
     * Actualiza una ubicación existente.
     */
    public function update(Request $request, Ubicacion $ubicacion): JsonResponse
    {
        // Verificar que pertenezca al profesional autenticado
        if ($ubicacion->profesional_id !== Auth::user()->profesional?->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validados = $request->validate([
            'nombre'     => 'sometimes|string|max:255',
            'latitud'    => 'sometimes|numeric|between:-90,90',
            'longitud'   => 'sometimes|numeric|between:-180,180',
            'direccion'  => 'nullable|string|max:255',
            'ciudad'     => 'nullable|string|max:255',
            'pais'       => 'nullable|string|max:255',
        ]);

        $ubicacion->update($validados);

        return response()->json($ubicacion);
    }

    /**
     * DELETE /api/ubicaciones/{ubicacion}
     * Elimina una ubicación (también se elimina de todos los servicios).
     */
    public function destroy(Ubicacion $ubicacion): JsonResponse
    {
        // Verificar que pertenezca al profesional autenticado
        if ($ubicacion->profesional_id !== Auth::user()->profesional?->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $ubicacion->delete();

        return response()->json(['mensaje' => 'Ubicación eliminada correctamente']);
    }

    /**
     * GET /api/servicios/{servicio}/ubicaciones
     * Obtiene las ubicaciones asignadas a un servicio específico.
     */
    public function obtenerServicio(Servicio $servicio): JsonResponse
    {
        $this->authorize('view', $servicio);

        $ubicaciones = $servicio->ubicacion ? [$servicio->ubicacion] : [];
        return response()->json($ubicaciones);
    }

    /**
     * POST /api/servicios/{servicio}/ubicaciones/{ubicacion}
     * Asigna una ubicación a un servicio.
     */
    public function asignarServicio(Servicio $servicio, Ubicacion $ubicacion): JsonResponse
    {
        $this->authorize('manage', $servicio->profesional);

        // Verificar que la ubicación pertenezca al mismo profesional
        if ($ubicacion->profesional_id !== $servicio->profesional_id) {
            return response()->json(['error' => 'La ubicación no pertenece a este profesional'], 403);
        }

        if ($servicio->ubicacion_id === $ubicacion->id) {
            return response()->json(['error' => 'Esta ubicación ya es la principal del servicio'], 409);
        }

        $servicio->ubicacion()->associate($ubicacion);
        $servicio->save();

        return response()->json(['mensaje' => 'Ubicación asignada al servicio'], 201);
    }

    /**
     * DELETE /api/servicios/{servicio}/ubicaciones/{ubicacion}
     * Desasigna una ubicación de un servicio.
     */
    public function desasignarServicio(Servicio $servicio, Ubicacion $ubicacion): JsonResponse
    {
        $this->authorize('manage', $servicio->profesional);

        if ($servicio->ubicacion_id !== $ubicacion->id) {
            return response()->json(['error' => 'La ubicación no está asignada a este servicio'], 404);
        }

        $servicio->ubicacion()->dissociate();
        $servicio->save();

        return response()->json(['mensaje' => 'Ubicación removida del servicio']);
    }

    /**
     * Reverse geocoding usando OpenStreetMap Nominatim API (gratuita, sin clave requerida).
     * Retorna dirección, ciudad y país basado en lat/lng.
     */
    private function reverseGeocode($latitud, $longitud): ?array
    {
        try {
            $url = "https://nominatim.openstreetmap.org/reverse?lat={$latitud}&lon={$longitud}&format=json&zoom=18&addressdetails=1";
            
            $response = file_get_contents($url);
            $data = json_decode($response, true);

            if (!$data || !isset($data['address'])) {
                return null;
            }

            $address = $data['address'];
            
            return [
                'direccion' => $address['road'] ?? $address['street'] ?? $address['suburb'] ?? null,
                'ciudad'    => $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
                'pais'      => $address['country'] ?? null,
            ];
        } catch (\Exception $e) {
            \Log::warning('Reverse geocoding error', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
