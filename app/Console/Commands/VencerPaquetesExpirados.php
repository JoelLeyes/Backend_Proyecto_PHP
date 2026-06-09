<?php

namespace App\Console\Commands;

use App\Models\PaqueteCliente;
use Illuminate\Console\Command;

class VencerPaquetesExpirados extends Command
{
    protected $signature   = 'paquetes:vencer';
    protected $description = 'Marca como vencidos los paquetes activos cuya fecha de vencimiento ya pasó.';

    public function handle(): int
    {
        $vencidos = PaqueteCliente::where('estado', 'activo')
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->update(['estado' => 'vencido']);

        $this->info("Paquetes vencidos: {$vencidos}");

        return Command::SUCCESS;
    }
}
