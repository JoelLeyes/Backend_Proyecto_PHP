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
    protected $description = 'Envía recordatorio de cita al cliente una hora antes del turno.';

    public function __construct(private NotificacionService $notificaciones)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Traer reservas activas futuras que aún no recibieron recordatorio
        // y calcular si ya entraron en la ventana de una hora antes del turno.
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reserva> $candidatas */
        $candidatas = Reserva::with(['servicio.profesional', 'cliente', 'profesional'])
            ->whereIn('estado', ['confirmada', 'pagada'])
            ->whereNull('recordatorio_enviado_at')
            ->where('fecha_hora', '>', now())
            ->get();

        $enviados = 0;

        foreach ($candidatas as $reserva) {
            /** @var \App\Models\Reserva $reserva */
            $momentoEnvio = Carbon::parse($reserva->fecha_hora)->subHour();

            if ($momentoEnvio->lte(now())) {
                $this->enviarRecordatorio($reserva);
                $reserva->update(['recordatorio_enviado_at' => now()]);
                $enviados++;
            }
        }

        $this->info("Recordatorios enviados: {$enviados}");

        return Command::SUCCESS;
    }

    private function enviarRecordatorio(Reserva $reserva): void
    {
        $servicio  = $reserva->servicio->nombre;
        $profesional = $reserva->profesional->name;
        $fechaHora = Carbon::parse($reserva->fecha_hora)
            ->setTimezone(config('app.timezone', 'America/Montevideo'))
            ->format('d/m/Y \a \l\a\s H:i');

        // Notificación campana persistente
        NotificacionApp::crear(
            $reserva->cliente_id,
            'info',
            '🔔',
            'Recordatorio de cita',
            "Falta una hora para tu sesión de {$servicio} con {$profesional}. Es el {$fechaHora}."
        );

        // Email vía microservicio de notificaciones
        $this->notificaciones->recordatorioReserva($reserva);
    }
}
