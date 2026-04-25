<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Eloquent para la tabla "profesionales".
 * Extiende el perfil de un usuario con información de negocio,
 * ubicación geográfica y política de cancelación.
 */
class Profesional extends Model
{
    use HasFactory;

    protected $table = 'profesionales';

    protected $fillable = [
        'user_id',
        'nombre_negocio',
        'bio',
        'modalidad',
        'direccion',
        'ciudad',
        'pais',
        'latitud',
        'longitud',
        'horas_cancelacion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'latitud'  => 'float',
            'longitud' => 'float',
            'activo'   => 'boolean',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────────────

    /**
     * Un profesional pertenece a un usuario.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Un profesional tiene muchos servicios.
     */
    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'profesional_id');
    }

    /**
     * Un profesional tiene muchas reglas de disponibilidad.
     */
    public function reglasDisponibilidad(): HasMany
    {
        return $this->hasMany(ReglaDisponibilidad::class, 'profesional_id');
    }

    /**
     * Un profesional tiene muchas excepciones de disponibilidad.
     */
    public function excepcionesDisponibilidad(): HasMany
    {
        return $this->hasMany(ExcepcionDisponibilidad::class, 'profesional_id');
    }
}
