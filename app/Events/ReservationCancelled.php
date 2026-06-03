<?php

namespace App\Events;

use App\Models\Reserva;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Reserva $reserva)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('reservations.' . $this->reserva->cliente_id),
            new PrivateChannel('reservations.' . $this->reserva->profesional_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reservation.cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'reserva' => $this->reserva->load(['servicio', 'cliente', 'profesional']),
            'tipo' => 'Reserva cancelada',
        ];
    }
}
