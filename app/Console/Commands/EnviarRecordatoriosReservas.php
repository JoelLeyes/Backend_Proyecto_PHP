<?php

namespace App\Console\Commands;

use App\Models\NotificacionApp;
use App\Models\Reserva;
use App\Services\NotificacionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnviarRecordatoriosReservas extends Command
{
    protected $signature   = 'reservas:recordatorios';
    protected $description = 'Envía recordatorio de cita al cliente X horas antes (horas_cancelacion + 3).';

    public function __construct(private NotificacionService $notificaciones)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Traer reservas activas futuras que aún no recibieron recordatorio
        $candidatas = Reserva::with(['servicio.profesional', 'cliente', 'profesional'])
            ->whereIn('estado', ['confirmada', 'pagada'])
            ->whereNull('recordatorio_enviado_at')
            ->where('fecha_hora', '>', now())
            ->get();

        $enviados = 0;

        foreach ($candidatas as $reserva) {
            $horasCancelacion = $reserva->servicio?->profesional?->horas_cancelacion ?? 0;
            $horasAviso       = $horasCancelacion + 3;
            $momentoEnvio     = Carbon::parse($reserva->fecha_hora)->subHours($horasAviso);

            if ($momentoEnvio->lte(now())) {
                $this->enviarRecordatorio($reserva, $horasCancelacion);
                $reserva->update(['recordatorio_enviado_at' => now()]);
                $enviados++;
            }
        }

        $this->info("Recordatorios enviados: {$enviados}");

        return Command::SUCCESS;
    }

    private function enviarRecordatorio(Reserva $reserva, int $horasCancelacion): void
    {
        $servicio  = $reserva->servicio->nombre;
        $profesional = $reserva->profesional->name;
        $fechaHora = Carbon::parse($reserva->fecha_hora)
            ->setTimezone(config('app.timezone', 'America/Montevideo'))
            ->format('d/m/Y \a \l\a\s H:i');

        $aviso = $horasCancelacion > 0
            ? " Tenés {$horasCancelacion}h para cancelar o reprogramar si lo necesitás."
            : '';

        // Notificación campana persistente
        NotificacionApp::crear(
            $reserva->cliente_id,
            'info',
            '🔔',
            'Recordatorio de cita',
            "Tu sesión de {$servicio} con {$profesional} es el {$fechaHora}.{$aviso}"
        );

        // Email vía microservicio de notificaciones
        $this->notificaciones->recordatorioReserva($reserva);
    }
}
