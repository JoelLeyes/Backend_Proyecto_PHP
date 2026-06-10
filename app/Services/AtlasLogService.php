<?php

namespace App\Services;

use MongoDB\Client;
use Throwable;
use Illuminate\Support\Facades\Log;

class AtlasLogService
{
    public function registrarConexion(string $email, bool $exito, array $contexto = []): void
    {
        $this->registrar('conexion', $exito ? 'Conexion exitosa' : 'Conexion fallida', $email, $contexto, [
            'status' => $exito ? 'success' : 'failed',
        ]);
    }

    public function registrarError(Throwable $e, array $contexto = []): void
    {
        $this->registrar('error', $e->getMessage(), null, $contexto, [
            'error_class' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    public function registrarEmailBienvenida(string $email, bool $exito, array $contexto = []): void
    {
        $this->registrar(
            'email_bienvenida',
            $exito ? 'Email de bienvenida encolado' : 'Email de bienvenida fallido',
            $email,
            $contexto,
            ['status' => $exito ? 'queued' : 'failed']
        );
    }

    public function registrarCreacionUsuario(?string $email, bool $exito, array $contexto = []): void
    {
        $this->registrar(
            'creacion_usuario',
            $exito ? 'Creación de usuario exitosa' : 'Creación de usuario fallida',
            $email,
            $contexto,
            [
                'status' => $exito ? 'success' : 'failed',
            ]
        );
    }

    private function registrar(string $tipo, string $message, ?string $email, array $contexto, array $extra = []): void
    {
        if (!$this->habilitado()) {
            return;
        }

        $document = array_filter([
            'tipo' => $tipo,
            'created_at' => now()->toIso8601String(),
            'message' => $message,
            'user_email' => $email,
            'route' => $this->routeActual(),
            'method' => request()?->method(),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'contexto' => array_merge($contexto, $extra),
        ], static fn ($value) => $value !== null && $value !== '');

        try {
            $client = new Client(config('services.atlas_logs.mongodb_uri'));

            $client
                ->selectDatabase(config('services.atlas_logs.database'))
                ->selectCollection(config('services.atlas_logs.collection'))
                ->insertOne($document);
        } catch (Throwable $e) {
            Log::warning('No se pudo escribir en Atlas MongoDB: ' . $e->getMessage());
        }
    }

    private function habilitado(): bool
    {
        return (bool) config('services.atlas_logs.enabled')
            && config('services.atlas_logs.mongodb_uri');
    }

    private function routeActual(): ?string
    {
        return request()?->path();
    }
}