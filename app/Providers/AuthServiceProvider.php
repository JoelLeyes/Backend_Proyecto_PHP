<?php

namespace App\Providers;

use App\Models\Profesional;
use App\Models\Reserva;
use App\Policies\ProfesionalPolicy;
use App\Policies\ReservaPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Profesional::class => ProfesionalPolicy::class,
        Reserva::class     => ReservaPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('admin', fn($user) => $user->esAdmin());
    }
}
