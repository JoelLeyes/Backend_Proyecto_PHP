<?php

namespace App\Console;

use App\Console\Commands\EnviarRecordatoriosReservas;
use App\Console\Commands\FinalizarReservasVencidas;
use App\Console\Commands\LimpiarNotificacionesAntiguas;
use App\Console\Commands\VencerPaquetesExpirados;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Console\Scheduling\Schedule;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        FinalizarReservasVencidas::class,
        VencerPaquetesExpirados::class,
        EnviarRecordatoriosReservas::class,
        LimpiarNotificacionesAntiguas::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('reservas:finalizar')->everyFiveMinutes();
        $schedule->command('reservas:recordatorios')->everyFifteenMinutes();
        $schedule->command('paquetes:vencer')->dailyAt('00:05');
        $schedule->command('notificaciones:limpiar')->dailyAt('03:00');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
