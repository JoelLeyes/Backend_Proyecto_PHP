<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job para enviar notificaciones al microservicio de forma asíncrona.
 * Se ejecuta en el worker de Redis y reintenta hasta 3 veces con backoff.
 */
class EnviarNotificacion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly string $tipo,
        private readonly string $email,
        private readonly string $nombre,
        private readonly array  $datos,
        private readonly string $url,
        private readonly string $token,
    ) {}

    public function handle(): void
    {
        Http::withHeaders(['X-Token-Servicio' => $this->token])
            ->timeout(10)
            ->post("{$this->url}/api/notificar", [
                'tipo'           => $this->tipo,
                'email_usuario'  => $this->email,
                'nombre_usuario' => $this->nombre,
                'datos'          => $this->datos,
            ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning(
            "Notificación fallida [{$this->tipo}] para {$this->email} tras {$this->tries} intentos: " .
            $exception->getMessage()
        );
    }
}
