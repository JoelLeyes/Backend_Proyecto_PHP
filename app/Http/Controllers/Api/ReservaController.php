<?php

namespace App\Http\Controllers\Api;

use App\Events\ReservaActualizada;
use App\Http\Controllers\Controller;
use App\Models\NotificacionApp;
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
        $consulta = Reserva::with(['servicio.profesional', 'servicio.ubicacion', 'cliente', 'profesional', 'resena']);

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
            'modalidad'         => 'required|in:presencial,remota',
            'notas'             => 'nullable|string|max:500',
            'paquete_cliente_id' => 'nullable|exists:paquetes_cliente,id',
        ]);

        $servicio = Servicio::with('profesional')->findOrFail($validados['servicio_id']);

        // Validar que la modalidad sea compatible con el servicio
        if ($servicio->modalidad !== 'hibrida' && $validados['modalidad'] !== $servicio->modalidad) {
            return response()->json([
                'error' => "Este servicio solo acepta modalidad {$servicio->modalidad}.",
            ], 422);
        }

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
            // Verificar sesiones libres: usadas + reservas activas pendientes de ocurrir
            if ($paqueteCliente->estado !== 'activo') {
                return response()->json(['error' => 'Este paquete no está activo.'], 422);
            }
            $reservasActivasConPaquete = Reserva::where('paquete_cliente_id', $paqueteCliente->id)
                ->whereNotIn('estado', ['cancelada', 'no_asistida', 'finalizada'])
                ->count();
            $sesionesLibres = $paqueteCliente->sesiones_total
                - $paqueteCliente->sesiones_usadas
                - $reservasActivasConPaquete;
            if ($sesionesLibres <= 0) {
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

            $reservasCandidatas = Reserva::where('profesional_id', $servicio->profesional->user_id)
                ->whereNotIn('estado', ['cancelada', 'no_asistida'])
                ->where('fecha_hora', '<', $fin)
                ->lockForUpdate()
                ->get();

            return $reservasCandidatas->contains(function (Reserva $reserva) use ($inicio) {
                $finReserva = Carbon::parse($reserva->fecha_hora)->addMinutes($reserva->duracion_minutos);

                return $finReserva->greaterThan($inicio);
            });
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

        $reserva->load(['servicio.profesional', 'cliente', 'profesional']);

        $servicio  = $reserva->servicio->nombre;
        $cliente   = $reserva->cliente->name;

        // Notificación campana → profesional: nueva reserva recibida
        NotificacionApp::crear(
            $reserva->profesional_id, 'info', '📅',
            'Nueva reserva',
            "Nueva reserva de {$cliente} para {$servicio}."
        );

        $this->notificaciones->reservaSolicitadaCliente($reserva);
        $this->notificaciones->reservaSolicitadaProfesional($reserva);

        ReservaActualizada::dispatch($reserva);

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

        // Notificación campana → cliente: su reserva fue confirmada
        NotificacionApp::crear(
            $reserva->cliente_id, 'success', '✅',
            'Reserva confirmada',
            "Tu reserva de {$reserva->servicio->nombre} fue confirmada."
        );

        $this->notificaciones->reservaConfirmada($reserva);

        ReservaActualizada::dispatch($reserva);

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

        $servicio = $reserva->servicio->nombre;

        // Notificación campana a la contraparte de quien canceló
        if ($request->user()->id === $reserva->cliente_id) {
            NotificacionApp::crear(
                $reserva->profesional_id, 'warning', '❌',
                'Reserva cancelada',
                "La reserva de {$reserva->cliente->name} para {$servicio} fue cancelada."
            );
        } else {
            NotificacionApp::crear(
                $reserva->cliente_id, 'error', '❌',
                'Reserva cancelada',
                "Tu reserva de {$servicio} fue cancelada."
            );
        }

        $this->notificaciones->reservaCancelada($reserva, $request->user());

        ReservaActualizada::dispatch($reserva);

        return response()->json($reserva);
    }

    /**
     * POST /api/reservas/{reserva}/finalizar
     * El profesional marca manualmente una reserva como finalizada.
     */
    public function finalizar(Reserva $reserva): JsonResponse
    {
        $this->authorize('manage', $reserva);

        if (!in_array($reserva->estado, ['confirmada', 'pagada', 'en_curso'])) {
            return response()->json(['error' => 'Solo se pueden finalizar reservas confirmadas, pagadas o en curso.'], 422);
        }

        $reserva->update(['estado' => 'finalizada']);
        $reserva->load(['servicio', 'cliente', 'profesional']);

        // Notificación campana → cliente: sesión finalizada
        NotificacionApp::crear(
            $reserva->cliente_id, 'info', '🏁',
            'Sesión finalizada',
            "Tu sesión de {$reserva->servicio->nombre} finalizó."
        );

        // Consumir sesión del paquete al ocurrir la sesión (no al reservar)
        if ($reserva->paquete_cliente_id) {
            $paquete = PaqueteCliente::find($reserva->paquete_cliente_id);
            if ($paquete && $paquete->estado === 'activo') {
                $nuevasUsadas = $paquete->sesiones_usadas + 1;
                $paquete->update([
                    'sesiones_usadas' => $nuevasUsadas,
                    'estado'          => $nuevasUsadas >= $paquete->sesiones_total ? 'consumido' : 'activo',
                ]);
            }
        }

        ReservaActualizada::dispatch($reserva);

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

        $reserva->update([
            'fecha_hora' => $validados['fecha_hora'],
            'estado' => 'confirmada',
            'recordatorio_enviado_at' => null,
        ]);
        $reserva->load(['servicio', 'cliente', 'profesional']);

        $servicio  = $reserva->servicio->nombre;
        $fechaHora = Carbon::parse($reserva->fecha_hora)
            ->setTimezone(config('app.timezone', 'America/Montevideo'))
            ->format('d/m/Y \a \l\a\s H:i');

        // Notificación campana → cliente y profesional: reserva reprogramada
        NotificacionApp::crear(
            $reserva->cliente_id, 'info', '📅',
            'Reserva reprogramada',
            "Tu reserva de {$servicio} fue reprogramada para el {$fechaHora}."
        );
        NotificacionApp::crear(
            $reserva->profesional_id, 'info', '📅',
            'Reserva reprogramada',
            "La reserva de {$reserva->cliente->name} para {$servicio} fue reprogramada al {$fechaHora}."
        );

        $this->notificaciones->reservaReprogramada($reserva);
        ReservaActualizada::dispatch($reserva, 'reprogramada');

        return response()->json($reserva);
    }
}
