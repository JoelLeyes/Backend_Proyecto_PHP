<?php

namespace App\Events;

use App\Models\PaqueteServicio;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaqueteServicioActualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PaqueteServicio $paquete,
        public string $accion = 'actualizado'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("servicios.{$this->paquete->servicio_id}.paquetes"),
            new PrivateChannel('admin.panel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'paquete.servicio.actualizado';
    }

    public function broadcastWith(): array
    {
        $paquete = $this->paquete->loadMissing(['servicio.profesional.usuario']);

        return [
            'accion'       => $this->accion,
            'servicio_id'  => $paquete->servicio_id,
            'paquete'      => $paquete->toArray(),
        ];
    }
}
