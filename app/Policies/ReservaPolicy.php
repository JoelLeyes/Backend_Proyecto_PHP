<?php

namespace App\Policies;

use App\Models\Reserva;
use App\Models\User;

class ReservaPolicy
{
    /**
     * Ver detalle, cancelar o reprogramar:
     * puede el cliente dueño, el profesional asignado o un admin.
     */
    public function view(User $user, Reserva $reserva): bool
    {
        return $user->id === $reserva->cliente_id
            || $user->id === $reserva->profesional_id
            || $user->esAdmin();
    }

    /**
     * Confirmar: solo el profesional asignado o un admin.
     */
    public function manage(User $user, Reserva $reserva): bool
    {
        return $user->id === $reserva->profesional_id || $user->esAdmin();
    }
}
