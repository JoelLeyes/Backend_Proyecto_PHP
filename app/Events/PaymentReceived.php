<?php

namespace App\Events;

use App\Models\Pago;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Pago $pago)
    {
    }

    public function broadcastOn(): array
    {
        $reserva = $this->pago->reserva;
        return [
            new PrivateChannel('reservations.' . $reserva->cliente_id),
            new PrivateChannel('reservations.' . $reserva->profesional_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.received';
    }

    public function broadcastWith(): array
    {
        return [
            'pago' => $this->pago->load('reserva.cliente'),
            'reserva' => $this->pago->reserva->load(['servicio', 'cliente', 'profesional']),
            'tipo' => 'Pago recibido',
        ];
    }
}
