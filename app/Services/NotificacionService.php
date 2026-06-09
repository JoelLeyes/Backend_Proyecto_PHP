<?php

namespace App\Services;

use App\Jobs\EnviarNotificacion;
use App\Models\Reserva;
use App\Models\User;

/**
 * Integración con el microservicio de notificaciones.
 * Las llamadas son tolerantes a fallos: un error en el servicio
 * nunca interrumpe el flujo principal de la reserva.
 */
class NotificacionService
{
    private string $url;
    private string $token;

    public function __construct()
    {
        $this->url   = rtrim(config('services.notificaciones.url', ''), '/');
        $this->token = config('services.notificaciones.token', '');
    }

    /**
     * Avisa al cliente que la solicitud de reserva fue recibida.
     */
    public function reservaSolicitadaCliente(Reserva $reserva): void
    {
        $cliente = $reserva->cliente;
        if (!$this->puedeNotificar($cliente)) return;

        $this->enviar('reserva_solicitada_cliente', $cliente->email, $cliente->name, [
            'nombre_servicio'    => $reserva->servicio->nombre,
            'fecha_hora'         => $this->formatear($reserva->fecha_hora),
            'nombre_profesional' => $reserva->profesional->name,
            'modalidad'          => $reserva->modalidad,
        ]);
    }

    /**
     * Avisa al profesional que tiene una nueva solicitud pendiente para confirmar.
     */
    public function reservaSolicitadaProfesional(Reserva $reserva): void
    {
        $profesional = $reserva->profesional;
        if (!$this->puedeNotificar($profesional)) return;

        $this->enviar('reserva_solicitada_profesional', $profesional->email, $profesional->name, [
            'nombre_servicio'    => $reserva->servicio->nombre,
            'fecha_hora'         => $this->formatear($reserva->fecha_hora),
            'nombre_cliente'     => $reserva->cliente->name,
            'modalidad'          => $reserva->modalidad,
            'notas'              => $reserva->notas,
        ]);
    }

    /**
     * Avisa al cliente que el profesional confirmó su reserva.
     */
    public function reservaConfirmada(Reserva $reserva): void
    {
        $cliente = $reserva->cliente;
        if (!$this->puedeNotificar($cliente)) return;

        $this->enviar('reserva_confirmada', $cliente->email, $cliente->name, [
            'nombre_servicio'    => $reserva->servicio->nombre,
            'fecha_hora'         => $this->formatear($reserva->fecha_hora),
            'nombre_profesional' => $reserva->profesional->name,
            'modalidad'          => $reserva->modalidad,
        ]);
    }

    /**
     * Avisa a la contraparte que la reserva fue cancelada.
     * Si cancela el cliente, avisa al profesional y viceversa.
     */
    public function reservaCancelada(Reserva $reserva, User $canceladoPor): void
    {
        $destinatario = $canceladoPor->id === $reserva->cliente_id
            ? $reserva->profesional
            : $reserva->cliente;

        if (!$this->puedeNotificar($destinatario)) return;

        $this->enviar('reserva_cancelada', $destinatario->email, $destinatario->name, [
            'nombre_servicio'    => $reserva->servicio->nombre,
            'fecha_hora'         => $this->formatear($reserva->fecha_hora),
            'motivo_cancelacion' => $reserva->motivo_cancelacion ?? 'Sin especificar',
        ]);
    }

    /**
     * Avisa al cliente que su reserva fue reprogramada.
     */
    public function reservaReprogramada(Reserva $reserva): void
    {
        $cliente = $reserva->cliente;
        if (!$this->puedeNotificar($cliente)) return;

        $this->enviar('reserva_reprogramada', $cliente->email, $cliente->name, [
            'nombre_servicio'    => $reserva->servicio->nombre,
            'nueva_fecha_hora'   => $this->formatear($reserva->fecha_hora),
            'nombre_profesional' => $reserva->profesional->name,
        ]);
    }

    /**
     * Envía el recordatorio de cita al cliente X horas antes.
     * X = horas_cancelacion del profesional + 3 (para que le dé tiempo de cancelar).
     */
    public function recordatorioReserva(Reserva $reserva): void
    {
        $cliente = $reserva->cliente;
        if (!$this->puedeNotificar($cliente)) return;

        $horasCancelacion = $reserva->servicio?->profesional?->horas_cancelacion ?? 0;
        $horasParaCancelar = $horasCancelacion;

        $this->enviar('recordatorio_reserva', $cliente->email, $cliente->name, [
            'nombre_servicio'    => $reserva->servicio->nombre,
            'fecha_hora'         => $this->formatear($reserva->fecha_hora),
            'nombre_profesional' => $reserva->profesional->name,
            'modalidad'          => $reserva->modalidad,
            'horas_cancelacion'  => $horasParaCancelar,
        ]);
    }

    /**
     * Avisa al profesional cuando recibe una nueva reseña.
     */
    public function resenaCreada(Reserva $reserva, mixed $resena): void
    {
        $profesional = $reserva->profesional;
        if (!$this->puedeNotificar($profesional)) return;

        $this->enviar('resena_creada', $profesional->email, $profesional->name, [
            'nombre_servicio' => $reserva->servicio->nombre,
            'nombre_cliente'  => $reserva->cliente->name,
            'fecha_hora'      => $this->formatear($reserva->fecha_hora),
            'calificacion'    => $resena->calificacion,
            'comentario'      => $resena->comentario,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function puedeNotificar(?User $usuario): bool
    {
        return $usuario !== null
            && ($usuario->notificaciones_email ?? true)
            && $this->url !== ''
            && $this->token !== '';
    }

    private function enviar(string $tipo, string $email, string $nombre, array $datos): void
    {
        EnviarNotificacion::dispatch($tipo, $email, $nombre, $datos, $this->url, $this->token);
    }

    private function formatear(mixed $fechaHora): string
    {
        try {
            return \Carbon\Carbon::parse($fechaHora)
                ->setTimezone(config('app.timezone', 'America/Montevideo'))
                ->translatedFormat('l j \d\e F \d\e Y \a \l\a\s H:i');
        } catch (\Throwable) {
            return (string) $fechaHora;
        }
    }
}
