<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaqueteCliente;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Services\NotificacionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador API para el recurso Reserva.
 * Gestiona el ciclo de vida completo de una reserva:
 * creación, confirmación, cancelación y reprogramación.
 * Incluye control de concurrencia para evitar doble reserva.
 */
class ReservaController extends Controller
{
    public function __construct(private NotificacionService $notificaciones) {}
    /**
     * GET /api/reservas
     * Lista las reservas del usuario autenticado.
     * Un cliente ve sus propias reservas; un profesional ve las que le corresponden.
     */
    public function index(Request $request): JsonResponse
    {
        $usuario  = $request->user();
        $consulta = Reserva::with(['servicio.profesional', 'cliente', 'profesional']);

        if ($usuario->esCliente()) {
            $consulta->where('cliente_id', $usuario->id);
        } elseif ($usuario->esProfesional()) {
            $consulta->where('profesional_id', $usuario->id);
        }

        if ($request->filled('estado')) {
            $consulta->where('estado', $request->estado);
        }

        $reservas = $consulta->orderByDesc('fecha_hora')->paginate(20);

        return response()->json($reservas);
    }

    /**
     * POST /api/reservas
     * Crea una nueva reserva para el usuario autenticado.
     * Usa bloqueo de transacción (lockForUpdate) para evitar doble reserva.
     */
    public function store(Request $request): JsonResponse
    {
        $validados = $request->validate([
            'servicio_id'       => 'required|exists:servicios,id',
            'fecha_hora'        => 'required|date|after:now',
            'modalidad'         => 'required|in:presencial,remota,hibrida',
            'notas'             => 'nullable|string|max:500',
            'paquete_cliente_id' => 'nullable|exists:paquetes_cliente,id',
        ]);

        $servicio = Servicio::with('profesional')->findOrFail($validados['servicio_id']);

        // Validar paquete si se proporcionó
        $paqueteCliente = null;
        if (!empty($validados['paquete_cliente_id'])) {
            $paqueteCliente = PaqueteCliente::with('paqueteServicio')
                ->where('id', $validados['paquete_cliente_id'])
                ->where('cliente_id', $request->user()->id)
                ->first();

            if (!$paqueteCliente) {
                return response()->json(['error' => 'El paquete no existe o no te pertenece.'], 422);
            }
            if (!$paqueteCliente->tieneSesionesDisponibles()) {
                return response()->json(['error' => 'Este paquete no tiene sesiones disponibles.'], 422);
            }
            if ($paqueteCliente->paqueteServicio->servicio_id !== $servicio->id) {
                return response()->json(['error' => 'El paquete no corresponde al servicio seleccionado.'], 422);
            }
        }

        // Control de concurrencia: evitar doble reserva del mismo horario
        $hayConflicto = DB::transaction(function () use ($validados, $servicio) {
            $inicio = Carbon::parse($validados['fecha_hora']);
            $fin    = $inicio->copy()->addMinutes($servicio->duracion_minutos);

            return Reserva::where('profesional_id', $servicio->profesional->user_id)
                ->whereNotIn('estado', ['cancelada', 'no_asistida'])
                ->where('fecha_hora', '<', $fin)
                ->whereRaw("fecha_hora + (duracion_minutos * interval '1 minute') > ?", [$inicio])
                ->lockForUpdate()
                ->exists();
        });

        if ($hayConflicto) {
            return response()->json(['error' => 'El horario seleccionado ya no está disponible.'], 409);
        }

        $reserva = Reserva::create([
            'servicio_id'        => $servicio->id,
            'cliente_id'         => $request->user()->id,
            'profesional_id'     => $servicio->profesional->user_id,
            'paquete_cliente_id' => $validados['paquete_cliente_id'] ?? null,
            'fecha_hora'         => $validados['fecha_hora'],
            'duracion_minutos'   => $servicio->duracion_minutos,
            'estado'             => 'pendiente',
            'modalidad'          => $validados['modalidad'],
            'notas'              => $validados['notas'] ?? null,
        ]);

        // Consumir sesión del paquete si se usó uno
        if ($paqueteCliente) {
            $paqueteCliente->increment('sesiones_usadas');
            if ($paqueteCliente->sesiones_usadas >= $paqueteCliente->sesiones_total) {
                $paqueteCliente->update(['estado' => 'consumido']);
            }
        }

        return response()->json($reserva->load(['servicio', 'cliente', 'profesional']), 201);
    }

    /**
     * GET /api/reservas/{reserva}
     * Muestra el detalle completo de una reserva.
     */
    public function show(Reserva $reserva): JsonResponse
    {
        $this->authorize('view', $reserva);

        $reserva->load(['servicio', 'cliente', 'profesional', 'resena', 'sesionVideo', 'pagos']);

        return response()->json($reserva);
    }

    /**
     * POST /api/reservas/{reserva}/confirmar
     * El profesional confirma una reserva pendiente.
     */
    public function confirmar(Reserva $reserva): JsonResponse
    {
        $this->authorize('manage', $reserva);

        if ($reserva->estado !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden confirmar reservas pendientes.'], 422);
        }

        $reserva->update(['estado' => 'confirmada']);
        $reserva->load(['servicio', 'cliente', 'profesional']);

        $this->notificaciones->reservaConfirmada($reserva);

        return response()->json($reserva);
    }

    /**
     * POST /api/reservas/{reserva}/cancelar
     * Cancela una reserva. Puede hacerlo el cliente o el profesional.
     */
    public function cancelar(Request $request, Reserva $reserva): JsonResponse
    {
        $this->authorize('view', $reserva);

        $estadosPermitidos = ['pendiente', 'confirmada', 'pagada'];

        if (!in_array($reserva->estado, $estadosPermitidos)) {
            return response()->json(['error' => 'Esta reserva no puede cancelarse en su estado actual.'], 422);
        }

        // Verificar política de cancelación: si cancela el cliente, respetar horas mínimas
        if ($request->user()->id === $reserva->cliente_id) {
            $profesional    = $reserva->servicio?->profesional;
            $horasMinimas   = $profesional?->horas_cancelacion ?? 0;
            $horasRestantes = now()->diffInHours(Carbon::parse($reserva->fecha_hora), false);

            if ($horasRestantes < $horasMinimas) {
                return response()->json([
                    'error' => "No podés cancelar con menos de {$horasMinimas} horas de anticipación.",
                ], 422);
            }
        }

        $reserva->update([
            'estado'             => 'cancelada',
            'fecha_cancelacion'  => now(),
            'cancelado_por'      => $request->user()->id,
            'motivo_cancelacion' => $request->input('motivo'),
        ]);
        $reserva->load(['servicio', 'cliente', 'profesional']);

        // Devolver sesión al paquete si la reserva usaba uno
        if ($reserva->paquete_cliente_id) {
            $paquete = PaqueteCliente::find($reserva->paquete_cliente_id);
            if ($paquete && $paquete->sesiones_usadas > 0) {
                $nuevasUsadas = $paquete->sesiones_usadas - 1;
                $paquete->update([
                    'sesiones_usadas' => $nuevasUsadas,
                    'estado'          => $nuevasUsadas < $paquete->sesiones_total ? 'activo' : 'consumido',
                ]);
            }
        }

        $this->notificaciones->reservaCancelada($reserva, $request->user());

        return response()->json($reserva);
    }

    /**
     * PATCH /api/reservas/{reserva}/reprogramar
     * Reprograma una reserva a una nueva fecha y hora.
     */
    public function reprogramar(Request $request, Reserva $reserva): JsonResponse
    {
        $this->authorize('view', $reserva);

        $validados = $request->validate([
            'fecha_hora' => 'required|date|after:now',
        ]);

        if (!in_array($reserva->estado, ['pendiente', 'confirmada'])) {
            return response()->json(['error' => 'Esta reserva no puede reprogramarse.'], 422);
        }

        $reserva->update(['fecha_hora' => $validados['fecha_hora'], 'estado' => 'confirmada']);
        $reserva->load(['servicio', 'cliente', 'profesional']);

        $this->notificaciones->reservaReprogramada($reserva);

        return response()->json($reserva);
    }
}
