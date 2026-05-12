<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        try {
            Http::withHeader('X-Token-Servicio', $this->token)
                ->timeout(5)
                ->post("{$this->url}/api/notificar", [
                    'tipo'           => $tipo,
                    'email_usuario'  => $email,
                    'nombre_usuario' => $nombre,
                    'datos'          => $datos,
                ]);
        } catch (\Throwable $e) {
            Log::warning("Notificación fallida [{$tipo}] para {$email}: {$e->getMessage()}");
        }
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
