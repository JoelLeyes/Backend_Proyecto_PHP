<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla "resenas".
 * Un cliente puede dejar una sola reseña por reserva finalizada.
 * La calificación (1 a 5) actualiza el promedio del profesional.
 */
class Resena extends Model
{
    use HasFactory;

    protected $table = 'resenas';

    protected $fillable = [
        'reserva_id',
        'evaluador_id',
        'profesional_id',
        'calificacion',
        'comentario',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────────────

    /**
     * La reseña pertenece a una reserva.
     */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    /**
     * El evaluador es el cliente que dejó la reseña.
     */
    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }

    /**
     * El profesional que recibió la reseña.
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }
}
