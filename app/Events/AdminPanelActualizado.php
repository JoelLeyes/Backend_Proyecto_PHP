<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminPanelActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $seccion = 'general',
        public string $accion = 'actualizado'
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.panel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'admin.panel.actualizado';
    }

    public function broadcastWith(): array
    {
        return [
            'seccion' => $this->seccion,
            'accion' => $this->accion,
        ];
    }
}
