<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notificación persistente para el historial de la campana del usuario.
 */
class NotificacionApp extends Model
{
    protected $table = 'notificaciones_app';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'icono',
        'titulo',
        'mensaje',
        'leida',
    ];

    protected function casts(): array
    {
        return ['leida' => 'boolean'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Crea y persiste una notificación para un usuario.
     */
    public static function crear(int $usuarioId, string $tipo, string $icono, string $titulo, string $mensaje): void
    {
        static::create([
            'usuario_id' => $usuarioId,
            'tipo'       => $tipo,
            'icono'      => $icono,
            'titulo'     => $titulo,
            'mensaje'    => $mensaje,
        ]);
    }
}
