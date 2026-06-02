<?php

use App\Jobs\ConsultaColaJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

Artisan::command('inspire', function () {
    $this->comment('Inspired!');
})->describe('Display an inspiring quote');

Artisan::command('colas:probar {cantidad=100 : Cantidad de jobs a despachar} {--esperar=true : Esperar hasta que la cola quede vacia}', function () {
    $cantidad = max(1, (int) $this->argument('cantidad'));
    $esperar = filter_var($this->option('esperar'), FILTER_VALIDATE_BOOLEAN);

    $this->info("Despachando {$cantidad} jobs a la cola redis...");

    for ($i = 1; $i <= $cantidad; $i++) {
        ConsultaColaJob::dispatch($i);

        if ($i % 25 === 0 || $i === $cantidad) {
            $this->line("Despachados {$i}/{$cantidad}");
        }
    }

    if ($esperar) {
        $this->info('Esperando a que la cola se vacie...');

        $maxSeconds = 1200;
        $elapsed = 0;

        while ($elapsed < $maxSeconds) {
            $pending = Queue::size();

            $this->line("Jobs pendientes: {$pending}");

            if ($pending === 0) {
                break;
            }

            sleep(5);
            $elapsed += 5;
        }

        if ($elapsed >= $maxSeconds) {
            $this->warn('Se alcanzo el tiempo maximo de espera y aun quedan jobs pendientes.');
        }
    }

    $this->info('Prueba de colas finalizada.');
})->describe('Despacha jobs de consulta para probar Redis y el worker de colas');
