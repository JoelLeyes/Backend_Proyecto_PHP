<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modelo Eloquent para la tabla "pagos".
 * Usa relación polimórfica para asociarse a una Reserva o un PaqueteCliente.
 * La relación polimórfica se llama "pagable" (pagable_type + pagable_id).
 */
class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'pagable_type',
        'pagable_id',
        'pagador_id',
        'monto',
        'moneda',
        'estado',
        'pasarela',
        'id_transaccion',
        'fecha_pago',
    ];

    protected function casts(): array
    {
        return [
            'monto'      => 'decimal:2',
            'fecha_pago' => 'datetime',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────────────

    /**
     * El objeto al que corresponde el pago (Reserva o PaqueteCliente).
     * Esta es la relación polimórfica: pagable_type indica la clase
     * y pagable_id indica el id del registro correspondiente.
     */
    public function pagable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * El usuario que realizó el pago.
     */
    public function pagador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pagador_id');
    }
}
