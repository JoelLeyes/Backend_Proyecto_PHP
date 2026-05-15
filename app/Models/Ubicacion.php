<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Modelo Eloquent para la tabla "ubicaciones".
 * Almacena ubicaciones geográficas reutilizables por profesional.
 * Cada ubicación puede usarse en múltiples servicios.
 */
class Ubicacion extends Model
{
    use HasFactory;

    protected $table = 'ubicaciones';

    protected $fillable = [
        'profesional_id',
        'nombre',
        'direccion',
        'ciudad',
        'pais',
        'latitud',
        'longitud',
    ];

    protected function casts(): array
    {
        return [
            'latitud'  => 'float',
            'longitud' => 'float',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────────────

    /**
     * Una ubicación pertenece a un profesional.
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    /**
     * Una ubicación puede usarse en muchos servicios.
     */
    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'servicio_ubicacion', 'ubicacion_id', 'servicio_id');
    }
}
