<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Eloquent para la tabla "paquetes_servicio".
 * Define un paquete de múltiples sesiones que ofrece un profesional.
 * Ejemplo: "Paquete 6 sesiones" a precio especial.
 */
class PaqueteServicio extends Model
{
    use HasFactory;

    protected $table = 'paquetes_servicio';

    protected $fillable = [
        'servicio_id',
        'nombre',
        'descripcion',
        'cantidad_sesiones',
        'precio',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────────────

    /**
     * Un paquete de servicio pertenece a un servicio.
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    /**
     * Un paquete de servicio puede ser adquirido por muchos clientes.
     */
    public function paquetesCliente(): HasMany
    {
        return $this->hasMany(PaqueteCliente::class, 'paquete_servicio_id');
    }
}
