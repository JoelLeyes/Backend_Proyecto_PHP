<?php

namespace App\Jobs;

use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConsultaColaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        private readonly int $numero,
    ) {}

    public function handle(): void
    {
        $usuarios = User::count();
        $servicios = Servicio::count();
        $reservas = Reserva::count();

        Log::info('Consulta de cola procesada', [
            'job' => $this->numero,
            'usuarios' => $usuarios,
            'servicios' => $servicios,
            'reservas' => $reservas,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('Consulta de cola fallida', [
            'job' => $this->numero,
            'error' => $exception->getMessage(),
        ]);
    }
}
