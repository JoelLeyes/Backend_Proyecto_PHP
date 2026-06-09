<?php

namespace App\Console\Commands;

use App\Models\NotificacionApp;
use Illuminate\Console\Command;

class LimpiarNotificacionesAntiguas extends Command
{
    protected $signature   = 'notificaciones:limpiar';
    protected $description = 'Elimina notificaciones de la campana con más de 90 días de antigüedad.';

    public function handle(): int
    {
        $eliminadas = NotificacionApp::where('created_at', '<', now()->subDays(90))->delete();

        $this->info("Notificaciones eliminadas: {$eliminadas}");

        return Command::SUCCESS;
    }
}
