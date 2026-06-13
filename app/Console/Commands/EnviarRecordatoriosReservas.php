<?php

namespace App\Console\Commands;

use App\Models\NotificacionApp;
use App\Models\Reserva;
use App\Services\NotificacionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnviarRecordatoriosReservas extends Command
{
    protected $signature   = 'reservas:recordatorios';
    protected $description = 'Envía recordatorios de cita: uno anticipado y otro una hora antes del turno.';

    public function __construct(private NotificacionService $notificaciones)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reserva> $candidatas */
        $candidatas = Reserva::with(['servicio.profesional', 'cliente', 'profesional'])
            ->whereIn('estado', ['confirmada', 'pagada'])
            ->where('fecha_hora', '>', now())
            ->where(function ($q) {
                $q->whereNull('recordatorio_anticipado_at')
                  ->orWhereNull('recordatorio_enviado_at');
            })
            ->get();

        $enviados = 0;

        foreach ($candidatas as $reserva) {
            /** @var \App\Models\Reserva $reserva */
            $horasCancelacion = $reserva->servicio?->profesional?->horas_cancelacion ?? 24;
            $horasAnticipado  = $horasCancelacion + 3;

            $momentoAnticipado = Carbon::parse($reserva->fecha_hora)->subHours($horasAnticipado);
            $momentoUnaHora    = Carbon::parse($reserva->fecha_hora)->subHour();

            // ── Recordatorio anticipado ────────────────────────────────────
            if (is_null($reserva->recordatorio_anticipado_at) && $momentoAnticipado->lte(now())) {
                $reserva->update(['recordatorio_anticipado_at' => now()]);

                try {
                    NotificacionApp::crear(
                        $reserva->cliente_id,
                        'info',
                        '🔔',
                        "Tu cita es en {$horasAnticipado} horas",
                        "Recordá que tenés una sesión de {$reserva->servicio->nombre} con {$reserva->profesional->name}.",
                    );
                } catch (\Throwable $e) {
                    Log::warning("Campana anticipada fallida para reserva {$reserva->id}: {$e->getMessage()}");
                }

                try {
                    $this->notificaciones->recordatorioAnticipado($reserva, $horasAnticipado);
                    $enviados++;
                } catch (\Throwable $e) {
                    Log::error("Email anticipado fallido para reserva {$reserva->id}: {$e->getMessage()}");
                    $this->warn("Reserva {$reserva->id} (anticipado): {$e->getMessage()}");
                }
            }

            // ── Recordatorio de una hora ───────────────────────────────────
            if (is_null($reserva->recordatorio_enviado_at) && $momentoUnaHora->lte(now())) {
                $reserva->update(['recordatorio_enviado_at' => now()]);

                try {
                    NotificacionApp::crear(
                        $reserva->cliente_id,
                        'info',
                        '🔔',
                        'Recordatorio de cita',
                        "Falta una hora para tu sesión de {$reserva->servicio->nombre} con {$reserva->profesional->name}.",
                    );
                } catch (\Throwable $e) {
                    Log::warning("Campana recordatorio fallida para reserva {$reserva->id}: {$e->getMessage()}");
                }

                try {
                    $this->notificaciones->recordatorioReserva($reserva);
                    $enviados++;
                } catch (\Throwable $e) {
                    Log::error("Email recordatorio fallido para reserva {$reserva->id}: {$e->getMessage()}");
                    $this->warn("Reserva {$reserva->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Recordatorios enviados: {$enviados}");

        return Command::SUCCESS;
    }
}
