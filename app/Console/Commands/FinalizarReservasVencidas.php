<?php

namespace App\Console\Commands;

use App\Models\PaqueteCliente;
use App\Models\Reserva;
use Illuminate\Console\Command;

class FinalizarReservasVencidas extends Command
{
    protected $signature   = 'reservas:finalizar';
    protected $description = 'Marca como finalizadas las reservas cuya fecha y duración ya pasaron y descuenta sesiones de paquetes.';

    public function handle(): int
    {
        $condicion = fn($q) => $q
            ->whereIn('estado', ['confirmada', 'pagada', 'en_curso'])
            ->whereRaw("fecha_hora + (duracion_minutos * interval '1 minute') <= NOW()");

        // Reservas con paquete: procesar una a una para descontar la sesión
        $conPaquete = Reserva::whereNotNull('paquete_cliente_id')
            ->tap($condicion)
            ->get();

        foreach ($conPaquete as $reserva) {
            $reserva->update(['estado' => 'finalizada']);
            $paquete = PaqueteCliente::find($reserva->paquete_cliente_id);
            if ($paquete && $paquete->estado === 'activo') {
                $nuevasUsadas = $paquete->sesiones_usadas + 1;
                $paquete->update([
                    'sesiones_usadas' => $nuevasUsadas,
                    'estado'          => $nuevasUsadas >= $paquete->sesiones_total ? 'consumido' : 'activo',
                ]);
            }
        }

        // Reservas sin paquete: bulk update eficiente
        $sinPaquete = Reserva::whereNull('paquete_cliente_id')
            ->tap($condicion)
            ->update(['estado' => 'finalizada']);

        $total = $conPaquete->count() + $sinPaquete;
        $this->info("Reservas finalizadas: {$total} (con paquete: {$conPaquete->count()}, sin paquete: {$sinPaquete})");

        return Command::SUCCESS;
    }
}
