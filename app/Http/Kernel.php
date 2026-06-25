<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [// Middleware globales que se ejecutan en cada solicitud
        \Illuminate\Http\Middleware\HandleCors::class,
    ];

    protected $middlewareGroups = [// Grupos de middleware para rutas web y API
        'web' => [],
        'api' => [
            'throttle:api',
            'bindings',
        ],
    ];

    protected $routeMiddleware = [// Middleware que se pueden asignar a rutas individuales
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
    ];
}
