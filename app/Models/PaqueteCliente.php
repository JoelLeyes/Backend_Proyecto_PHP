<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modelo Eloquent para la tabla "paquetes_cliente".
 * Registra un paquete de sesiones adquirido por un cliente.
 * Lleva el conteo de sesiones usadas vs. totales.
 */
class PaqueteCliente extends Model
{
    use HasFactory;

    protected $table = 'paquetes_cliente';

    protected $fillable = [
        'cliente_id',
        'paquete_servicio_id',
        'sesiones_total',
        'sesiones_usadas',
        'fecha_compra',
        'fecha_vencimiento',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_compra'      => 'datetime',
            'fecha_vencimiento' => 'date',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────────────

    /**
     * El paquete pertenece a un cliente.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    /**
     * El paquete fue creado a partir de un paquete de servicio.
     */
    public function paqueteServicio(): BelongsTo
    {
        return $this->belongsTo(PaqueteServicio::class, 'paquete_servicio_id');
    }

    /**
     * Las reservas que consumen este paquete.
     */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'paquete_cliente_id');
    }

    /**
     * Los pagos asociados a este paquete (relación polimórfica).
     */
    public function pagos(): MorphMany
    {
        return $this->morphMany(Pago::class, 'pagable');
    }

    /**
     * Verifica si el paquete tiene sesiones disponibles para usar.
     */
    public function tieneSesionesDisponibles(): bool
    {
        return $this->estado === 'activo' && $this->sesiones_usadas < $this->sesiones_total;
    }
}
