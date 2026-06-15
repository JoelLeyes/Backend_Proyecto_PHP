<?php

namespace App\Events;

use App\Models\Reserva;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservaActualizada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Reserva $reserva, public string $accion = '') {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("reservas.{$this->reserva->cliente_id}"),
            new PrivateChannel("reservas.{$this->reserva->profesional_id}"),
            new PrivateChannel('admin.panel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reserva.actualizada';
    }

    public function broadcastWith(): array
    {
        return [
            'reserva' => $this->reserva->load([
                'servicio.profesional',
                'servicio.ubicacion',
                'cliente',
                'profesional',
                'resena',
            ])->toArray(),
            'accion' => $this->accion,
        ];
    }
}
