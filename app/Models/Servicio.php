<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Eloquent para la tabla "servicios".
 * Un servicio pertenece a un profesional y puede tener paquetes y reservas.
 */
class Servicio extends Model
{
    use HasFactory;

    protected $table = 'servicios';

    protected $fillable = [
        'profesional_id',
        'nombre',
        'descripcion',
        'precio',
        'duracion_minutos',
        'modalidad',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio'  => 'decimal:2',
            'activo'  => 'boolean',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────────────

    /**
     * Un servicio pertenece a un profesional.
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    /**
     * Un servicio puede tener muchos paquetes de sesiones.
     */
    public function paquetes(): HasMany
    {
        return $this->hasMany(PaqueteServicio::class, 'servicio_id');
    }

    /**
     * Un servicio puede tener muchas reservas asociadas.
     */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'servicio_id');
    }
}
