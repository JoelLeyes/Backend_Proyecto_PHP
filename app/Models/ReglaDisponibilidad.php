<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla "reglas_disponibilidad".
 * Define los horarios laborales de un profesional para un día de la semana.
 * Los buffers permiten pausas automáticas entre turnos.
 */
class ReglaDisponibilidad extends Model
{
    use HasFactory;

    protected $table = 'reglas_disponibilidad';

    protected $fillable = [
        'profesional_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'buffer_antes_minutos',
        'buffer_despues_minutos',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Una regla de disponibilidad pertenece a un profesional.
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }
}
