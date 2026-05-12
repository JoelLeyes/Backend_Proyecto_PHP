<?php

namespace App\Policies;

use App\Models\Profesional;
use App\Models\User;

class ProfesionalPolicy
{
    /**
     * El usuario puede gestionar (crear servicios, disponibilidad, etc.)
     * si es el dueño del perfil profesional o es admin.
     */
    public function manage(User $user, Profesional $profesional): bool
    {
        return $user->id === $profesional->user_id || $user->esAdmin();
    }

    /**
     * El usuario puede actualizar el perfil profesional
     * si es el dueño o es admin.
     */
    public function update(User $user, Profesional $profesional): bool
    {
        return $user->id === $profesional->user_id || $user->esAdmin();
    }
}
