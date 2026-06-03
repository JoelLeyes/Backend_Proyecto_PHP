<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application uses. The required channels may be configured in the
| channels configuration file.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado por usuario: recibe actualizaciones de sus reservas
Broadcast::channel('reservas.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Canal privado para notificaciones de un profesional
Broadcast::channel('professional.{professionalId}', function ($user, $professionalId) {
    // El usuario debe ser el profesional o un admin
    return $user->id == $professionalId || $user->isAdmin();
});

// Canal privado para notificaciones de un cliente
Broadcast::channel('client.{clientId}', function ($user, $clientId) {
    // El usuario debe ser el cliente
    return $user->id == $clientId;
});
