<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent para la tabla "excepciones_disponibilidad".
 * Representa fechas específicas en las que el profesional no trabaja
 * (feriados, vacaciones) o agrega disponibilidad extra.
 */
class ExcepcionDisponibilidad extends Model
{
    use HasFactory;

    protected $table = 'excepciones_disponibilidad';

    protected $fillable = [
        'profesional_id',
        'fecha',
        'motivo',
        'disponible',
    ];

    protected function casts(): array
    {
        return [
            'fecha'      => 'date',
            'disponible' => 'boolean',
        ];
    }

    /**
     * Una excepción pertenece a un profesional.
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }
}
