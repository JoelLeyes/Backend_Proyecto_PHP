<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modelo de usuario del sistema.
 * El nombre de la tabla y los campos base (name, email, password)
 * se mantienen en inglés por convención interna de Laravel.
 * Los campos adicionales (rol, telefono, avatar) son en español.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
        'telefono',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ─── Helpers de rol ────────────────────────────────────────────────────

    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    public function esProfesional(): bool
    {
        return $this->rol === 'profesional';
    }

    public function esCliente(): bool
    {
        return $this->rol === 'cliente';
    }

    // ─── Relaciones ────────────────────────────────────────────────────────

    /**
     * El usuario puede tener un perfil profesional asociado.
     */
    public function profesional(): HasOne
    {
        return $this->hasOne(Profesional::class);
    }

    /**
     * Reservas en las que el usuario actúa como cliente.
     */
    public function reservasComoCliente(): HasMany
    {
        return $this->hasMany(Reserva::class, 'cliente_id');
    }

    /**
     * Reservas en las que el usuario actúa como profesional.
     */
    public function reservasComoProfesional(): HasMany
    {
        return $this->hasMany(Reserva::class, 'profesional_id');
    }

    public function paquetesCliente(): HasMany
    {
        return $this->hasMany(PaqueteCliente::class, 'cliente_id');
    }

    public function resenas(): HasMany
    {
        return $this->hasMany(Resena::class, 'evaluador_id');
    }
}
