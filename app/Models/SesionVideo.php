<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla "sesiones_video".
 * Almacena la sala y los tokens de videollamada para reservas remotas.
 * Se integra con LiveKit (o WebRTC) para la generación de tokens.
 */
class SesionVideo extends Model
{
    use HasFactory;

    protected $table = 'sesiones_video';

    protected $fillable = [
        'reserva_id',
        'nombre_sala',
        'token_cliente',
        'token_profesional',
        'iniciada_en',
        'finalizada_en',
    ];

    protected function casts(): array
    {
        return [
            'iniciada_en'   => 'datetime',
            'finalizada_en' => 'datetime',
        ];
    }

    /**
     * Una sesión de video pertenece a una reserva.
     */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }
}
