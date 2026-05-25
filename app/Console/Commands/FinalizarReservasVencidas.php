<?php

namespace App\Console\Commands;

use App\Models\Reserva;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinalizarReservasVencidas extends Command
{
    protected $signature   = 'reservas:finalizar';
    protected $description = 'Marca como finalizadas las reservas cuya fecha y duración ya pasaron.';

    public function handle(): int
    {
        $actualizadas = Reserva::whereIn('estado', ['confirmada', 'pagada', 'en_curso'])
            ->whereRaw("fecha_hora + (duracion_minutos * interval '1 minute') <= NOW()")
            ->update(['estado' => 'finalizada']);

        $this->info("Reservas finalizadas: {$actualizadas}");

        return Command::SUCCESS;
    }
}
