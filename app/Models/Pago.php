<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modelo de pago — registra cada transacción PayPal vinculada
 * a una reserva o a un paquete de cliente (relación polimórfica).
 */
class Pago extends Model // Modelo Eloquent para la tabla "pagos"
{
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [ // Atributos que se pueden asignar masivamente
        'pagable_type',
        'pagable_id',
        'cliente_id',
        'monto',
        'moneda',
        'paypal_order_id',
        'paypal_capture_id',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────────────

    public function pagable(): MorphTo // Relación polimórfica: un pago puede pertenecer a una reserva o a un paquete de cliente
    {
        return $this->morphTo();
    }

    public function cliente(): BelongsTo // Relación: un pago pertenece a un cliente (usuario)
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }
}
