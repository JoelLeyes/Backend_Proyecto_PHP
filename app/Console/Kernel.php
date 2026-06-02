<?php

namespace App\Console;

use App\Console\Commands\FinalizarReservasVencidas;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Console\Scheduling\Schedule;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        FinalizarReservasVencidas::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('reservas:finalizar')->everyFiveMinutes();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
