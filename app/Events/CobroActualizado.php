<?php

namespace App\Events;

use App\Models\Pago;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CobroActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Pago $pago,
        public int $profesionalId,
        public string $accion = 'completado'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("professional.{$this->profesionalId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'cobro.actualizado';
    }

    public function broadcastWith(): array
    {
        return [
            'accion' => $this->accion,
            'profesional_id' => $this->profesionalId,
            'pago' => $this->pago->loadMissing(['cliente', 'pagable'])->toArray(),
        ];
    }
}

