<?php

namespace App\Events;

use App\Models\NotificacionApp;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificacionAppCreada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public NotificacionApp $notificacion) {}

    public function broadcastOn(): array
    {
        $usuario = $this->notificacion->usuario;

        if (!$usuario) {
            return [];
        }

        if ($usuario->esAdmin()) {
            return [new PrivateChannel('admin.panel')];
        }

        if ($usuario->esProfesional() && $usuario->profesional) {
            return [new PrivateChannel("professional.{$usuario->profesional->id}")];
        }

        return [new PrivateChannel("client.{$usuario->id}")];
    }

    public function broadcastAs(): string
    {
        return 'notificacion.creada';
    }

    public function broadcastWith(): array
    {
        return [
            'notificacion' => [
                'id' => $this->notificacion->id,
                'usuario_id' => $this->notificacion->usuario_id,
                'tipo' => $this->notificacion->tipo,
                'icono' => $this->notificacion->icono,
                'titulo' => $this->notificacion->titulo,
                'mensaje' => $this->notificacion->mensaje,
                'leida' => (bool) $this->notificacion->leida,
                'created_at' => optional($this->notificacion->created_at)->toIso8601String(),
            ],
        ];
    }
}