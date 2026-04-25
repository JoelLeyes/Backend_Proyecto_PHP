<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaqueteCliente;
use App\Models\PaqueteServicio;
use App\Models\Profesional;
use App\Models\Servicio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para paquetes de servicio y paquetes de cliente.
 * Un profesional gestiona sus paquetes de servicio.
 * Un cliente puede comprar y ver sus paquetes adquiridos.
 */
class PaqueteController extends Controller
{
    // ─── Paquetes de servicio (los define el profesional) ──────────────────

    /**
     * GET /api/profesionales/{profesional}/servicios/{servicio}/paquetes
     * Lista los paquetes disponibles de un servicio.
     */
    public function index(Profesional $profesional, Servicio $servicio): JsonResponse
    {
        return response()->json($servicio->paquetes()->where('activo', true)->get());
    }

    /**
     * POST /api/profesionales/{profesional}/servicios/{servicio}/paquetes
     * Crea un nuevo paquete de sesiones para un servicio.
     */
    public function store(Request $request, Profesional $profesional, Servicio $servicio): JsonResponse
    {
        $this->authorize('manage', $profesional);

        $validados = $request->validate([
            'nombre'            => 'required|string|max:255',
            'descripcion'       => 'nullable|string',
            'cantidad_sesiones' => 'required|integer|min:2|max:100',
            'precio'            => 'required|numeric|min:0',
        ]);

        $paquete = $servicio->paquetes()->create($validados);

        return response()->json($paquete, 201);
    }

    /**
     * DELETE /api/profesionales/{profesional}/servicios/{servicio}/paquetes/{paquete}
     * Desactiva un paquete de servicio (no se elimina para preservar historial).
     */
    public function destroy(Profesional $profesional, Servicio $servicio, PaqueteServicio $paquete): JsonResponse
    {
        $this->authorize('manage', $profesional);

        $paquete->update(['activo' => false]);

        return response()->json(['mensaje' => 'Paquete desactivado correctamente.']);
    }

    // ─── Paquetes del cliente (los adquiere el cliente) ────────────────────

    /**
     * GET /api/mis-paquetes
     * Lista los paquetes adquiridos por el cliente autenticado.
     */
    public function misPaquetes(Request $request): JsonResponse
    {
        $paquetes = PaqueteCliente::where('cliente_id', $request->user()->id)
            ->with('paqueteServicio.servicio.profesional.usuario')
            ->orderByDesc('fecha_compra')
            ->get();

        return response()->json($paquetes);
    }

    /**
     * POST /api/paquetes-servicio/{paqueteServicio}/comprar
     * El cliente adquiere un paquete de sesiones.
     */
    public function comprar(Request $request, PaqueteServicio $paqueteServicio): JsonResponse
    {
        $paqueteCliente = PaqueteCliente::create([
            'cliente_id'          => $request->user()->id,
            'paquete_servicio_id' => $paqueteServicio->id,
            'sesiones_total'      => $paqueteServicio->cantidad_sesiones,
            'sesiones_usadas'     => 0,
            'fecha_compra'        => now(),
            'estado'              => 'activo',
        ]);

        return response()->json($paqueteCliente->load('paqueteServicio'), 201);
    }
}
