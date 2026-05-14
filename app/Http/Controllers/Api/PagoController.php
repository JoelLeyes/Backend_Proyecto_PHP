<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\PaqueteCliente;
use App\Models\Profesional;
use App\Models\PaqueteServicio;
use App\Models\Reserva;
use App\Services\PaypalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de pagos con PayPal.
 *
 * Flujo:
 *   1. iniciar()   → crea orden en PayPal, guarda Pago pendiente, devuelve paypal_order_id
 *   2. (frontend)  → muestra botones PayPal; usuario aprueba en el popup
 *   3. capturar()  → captura la orden, marca Pago como completado, activa la entidad
 */
class PagoController extends Controller
{
    // ── Iniciar pago ──────────────────────────────────────────────────────────

    /**
     * POST /api/pagos/iniciar
     * Crea una orden PayPal para una reserva existente o un paquete pendiente de pago.
     */
    public function iniciar(Request $request, PaypalService $paypal): JsonResponse
    {
        $validados = $request->validate([
            'tipo' => 'required|in:reserva,paquete',
            'id'   => 'required|integer|min:1',
        ]);

        [$entidad, $monto, $descripcion] = $this->resolverEntidad(
            $request->user()->id,
            $validados['tipo'],
            $validados['id']
        );

        // Evitar crear órdenes duplicadas para la misma entidad pendiente
        $pagoExistente = Pago::where('pagable_type', get_class($entidad))
            ->where('pagable_id', $entidad->id)
            ->where('estado', 'pendiente')
            ->first();

        if ($pagoExistente) {
            return response()->json([
                'paypal_order_id' => $pagoExistente->paypal_order_id,
                'monto'           => $monto,
                'pago_id'         => $pagoExistente->id,
            ]);
        }

        try {
            $paypalOrderId = $paypal->crearOrden($monto, 'USD', $descripcion);
        } catch (\Throwable) {
            return response()->json(['error' => 'No se pudo iniciar el pago. Intentá nuevamente.'], 502);
        }

        $pago = Pago::create([
            'pagable_type'    => get_class($entidad),
            'pagable_id'      => $entidad->id,
            'cliente_id'      => $request->user()->id,
            'monto'           => $monto,
            'moneda'          => 'USD',
            'paypal_order_id' => $paypalOrderId,
            'estado'          => 'pendiente',
        ]);

        return response()->json([
            'paypal_order_id' => $paypalOrderId,
            'monto'           => $monto,
            'pago_id'         => $pago->id,
        ]);
    }

    // ── Capturar pago ─────────────────────────────────────────────────────────

    /**
     * POST /api/pagos/capturar
     * Captura una orden PayPal aprobada. Activa la reserva o el paquete según corresponda.
     */
    public function capturar(Request $request, PaypalService $paypal): JsonResponse
    {
        $validados = $request->validate([
            'paypal_order_id' => 'required|string|max:100',
        ]);

        $pago = Pago::where('paypal_order_id', $validados['paypal_order_id'])
            ->where('cliente_id', $request->user()->id)
            ->where('estado', 'pendiente')
            ->firstOrFail();

        try {
            $resultado = $paypal->capturarOrden($validados['paypal_order_id']);
        } catch (\Throwable) {
            $pago->update(['estado' => 'fallido']);
            return response()->json(['error' => 'El pago no pudo procesarse. Intentá nuevamente.'], 502);
        }

        if (($resultado['status'] ?? '') !== 'COMPLETED') {
            $pago->update(['estado' => 'fallido']);
            return response()->json(['error' => 'El pago no fue aprobado por PayPal.'], 422);
        }

        $captureId = $resultado['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

        $pago->update([
            'estado'            => 'completado',
            'paypal_capture_id' => $captureId,
        ]);

        $this->activarEntidad($pago);

        return response()->json([
            'mensaje'           => 'Pago completado correctamente.',
            'paypal_capture_id' => $captureId,
        ]);
    }

    // ── Historial del cliente ─────────────────────────────────────────────────

    /**
     * GET /api/mis-pagos
     * Lista los pagos del cliente autenticado.
     */
    public function misPagos(Request $request): JsonResponse
    {
        $pagos = Pago::where('cliente_id', $request->user()->id)
            ->with('pagable')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($pagos);
    }

    // ── Pagos recibidos del profesional ───────────────────────────────────────

    /**
     * GET /api/profesionales/{profesional}/pagos
     * Lista los pagos recibidos por el profesional autenticado.
     */
    public function pagosRecibidos(Request $request, Profesional $profesional): JsonResponse
    {
        $this->authorize('manage', $profesional);

        // IDs de reservas del profesional
        $reservaIds = Reserva::where('profesional_id', $profesional->user_id)->pluck('id');

        // IDs de paquetes-cliente de servicios del profesional
        $servicioIds       = $profesional->servicios()->pluck('id');
        $paqueteServicioIds = PaqueteServicio::whereIn('servicio_id', $servicioIds)->pluck('id');
        $paqueteClienteIds  = PaqueteCliente::whereIn('paquete_servicio_id', $paqueteServicioIds)->pluck('id');

        $pagos = Pago::where('estado', 'completado')
            ->where(function ($q) use ($reservaIds, $paqueteClienteIds) {
                $q->where(function ($q2) use ($reservaIds) {
                    $q2->where('pagable_type', Reserva::class)
                        ->whereIn('pagable_id', $reservaIds);
                })->orWhere(function ($q2) use ($paqueteClienteIds) {
                    $q2->where('pagable_type', PaqueteCliente::class)
                        ->whereIn('pagable_id', $paqueteClienteIds);
                });
            })
            ->with(['cliente', 'pagable'])
            ->when($request->filled('desde'), fn($q) => $q->whereDate('created_at', '>=', $request->desde))
            ->when($request->filled('hasta'), fn($q) => $q->whereDate('created_at', '<=', $request->hasta))
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($pagos);
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Resuelve la entidad (Reserva o PaqueteCliente), el monto y la descripción
     * a partir del tipo y el ID. Lanza 404 si no pertenece al usuario.
     */
    private function resolverEntidad(int $clienteId, string $tipo, int $id): array
    {
        if ($tipo === 'reserva') {
            $reserva = Reserva::with('servicio')
                ->where('id', $id)
                ->where('cliente_id', $clienteId)
                ->whereIn('estado', ['pendiente', 'confirmada'])
                ->firstOrFail();

            return [
                $reserva,
                (float) $reserva->servicio->precio,
                "Reserva: {$reserva->servicio->nombre}",
            ];
        }

        $paquete = PaqueteCliente::with('paqueteServicio')
            ->where('id', $id)
            ->where('cliente_id', $clienteId)
            ->where('estado', 'pendiente_pago')
            ->firstOrFail();

        return [
            $paquete,
            (float) $paquete->paqueteServicio->precio,
            "Paquete: {$paquete->paqueteServicio->nombre} ({$paquete->sesiones_total} sesiones)",
        ];
    }

    /**
     * Activa la entidad luego de confirmarse el pago.
     * - Reserva → estado 'pagada'
     * - PaqueteCliente → estado 'activo'
     */
    private function activarEntidad(Pago $pago): void
    {
        $entidad = $pago->pagable;

        if ($entidad instanceof Reserva) {
            $entidad->update(['estado' => 'pagada']);
        } elseif ($entidad instanceof PaqueteCliente) {
            $entidad->update(['estado' => 'activo']);
        }
    }
}
