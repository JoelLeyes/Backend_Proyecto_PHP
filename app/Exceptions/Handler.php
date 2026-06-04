<?php

namespace App\Exceptions;

use App\Services\AtlasLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $levels = [
        // Custom error levels for exceptions.
    ];

    protected $dontReport = [
        // Exceptions that should not be reported.
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            try {
                app(AtlasLogService::class)->registrarError($e, [
                    'route' => request()?->path(),
                    'method' => request()?->method(),
                    'ip' => request()?->ip(),
                    'user_email' => request()?->user()?->email,
                ]);
            } catch (Throwable) {
                // No interrumpir el flujo si Atlas no responde.
            }
        });
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }
}
