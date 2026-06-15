<?php

namespace App\Console\Commands;

use App\Events\PaqueteClienteActualizado;
use App\Models\PaqueteCliente;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FinalizarReservasVencidas extends Command
{
    protected $signature   = 'reservas:finalizar';
    protected $description = 'Marca como finalizadas las reservas cuya fecha y duración ya pasaron y descuenta sesiones de paquetes.';

    public function handle(): int
    {
        $condicion = fn($q) => $q->whereIn('estado', ['confirmada', 'pagada', 'en_curso']);
        $ahora = now();

        // Reservas con paquete: procesar una a una para descontar la sesión
        $conPaquete = Reserva::whereNotNull('paquete_cliente_id')
            ->tap($condicion)
            ->get();

        $conPaquete = $conPaquete->filter(function (Reserva $reserva) use ($ahora) {
            $finReserva = Carbon::parse($reserva->fecha_hora)->addMinutes($reserva->duracion_minutos);

            return $finReserva->lessThanOrEqualTo($ahora);
        })->values();

        foreach ($conPaquete as $reserva) {
            $reserva->update(['estado' => 'finalizada']);
            $paquete = PaqueteCliente::find($reserva->paquete_cliente_id);
            if ($paquete && $paquete->estado === 'activo') {
                $nuevasUsadas = $paquete->sesiones_usadas + 1;
                $paquete->update([
                    'sesiones_usadas' => $nuevasUsadas,
                    'estado'          => $nuevasUsadas >= $paquete->sesiones_total ? 'consumido' : 'activo',
                ]);

                PaqueteClienteActualizado::dispatch($paquete->fresh(), 'sesion_consumida');
            }
        }

        // Reservas sin paquete: bulk update eficiente
        $sinPaquete = Reserva::whereNull('paquete_cliente_id')
            ->tap($condicion)
            ->get()
            ->filter(function (Reserva $reserva) use ($ahora) {
                $finReserva = Carbon::parse($reserva->fecha_hora)->addMinutes($reserva->duracion_minutos);

                return $finReserva->lessThanOrEqualTo($ahora);
            })->values();

        foreach ($sinPaquete as $reserva) {
            $reserva->update(['estado' => 'finalizada']);
        }

        $total = $conPaquete->count() + $sinPaquete->count();
        $this->info("Reservas finalizadas: {$total} (con paquete: {$conPaquete->count()}, sin paquete: {$sinPaquete->count()})");

        return Command::SUCCESS;
    }
}
