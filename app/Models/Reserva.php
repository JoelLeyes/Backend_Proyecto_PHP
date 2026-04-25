<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modelo Eloquent para la tabla "reservas".
 * Representa un turno entre un cliente y un profesional.
 *
 * Ciclo de vida del estado:
 *   pendiente → confirmada → pagada → en_curso → finalizada
 *                                              → cancelada
 *                                              → no_asistida
 */
class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = [
        'servicio_id',
        'cliente_id',
        'profesional_id',
        'paquete_cliente_id',
        'fecha_hora',
        'duracion_minutos',
        'estado',
        'modalidad',
        'notas',
        'fecha_cancelacion',
        'cancelado_por',
        'motivo_cancelacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora'        => 'datetime',
            'fecha_cancelacion' => 'datetime',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────────────

    /**
     * Una reserva pertenece a un servicio.
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    /**
     * Una reserva pertenece a un cliente (usuario con rol cliente).
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    /**
     * Una reserva pertenece a un profesional (usuario con rol profesional).
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    /**
     * Una reserva puede estar asociada a un paquete del cliente.
     */
    public function paqueteCliente(): BelongsTo
    {
        return $this->belongsTo(PaqueteCliente::class, 'paquete_cliente_id');
    }

    /**
     * Una reserva puede tener una sesión de video asociada (modalidad remota).
     */
    public function sesionVideo(): HasOne
    {
        return $this->hasOne(SesionVideo::class, 'reserva_id');
    }

    /**
     * Una reserva puede tener una reseña (solo si está finalizada).
     */
    public function resena(): HasOne
    {
        return $this->hasOne(Resena::class, 'reserva_id');
    }

    /**
     * Los pagos asociados a esta reserva (relación polimórfica).
     */
    public function pagos(): MorphMany
    {
        return $this->morphMany(Pago::class, 'pagable');
    }
}
