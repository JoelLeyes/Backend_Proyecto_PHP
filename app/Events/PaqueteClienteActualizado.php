<?php

namespace App\Events;

use App\Models\PaqueteCliente;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaqueteClienteActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PaqueteCliente $paquete,
        public string $accion = 'actualizado'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("client.{$this->paquete->cliente_id}"),
            new PrivateChannel('admin.panel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'paquete.cliente.actualizado';
    }

    public function broadcastWith(): array
    {
        return [
            'accion' => $this->accion,
            'paquete_cliente_id' => $this->paquete->id,
            'estado' => $this->paquete->estado,
            'cliente_id' => $this->paquete->cliente_id,
            'paquete_servicio_id' => $this->paquete->paquete_servicio_id,
        ];
    }
}
