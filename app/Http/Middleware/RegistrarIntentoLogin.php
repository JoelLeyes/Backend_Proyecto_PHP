<?php

namespace App\Http\Middleware;

use App\Services\AtlasLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RegistrarIntentoLogin
{
    public function __construct(private readonly AtlasLogService $atlasLogService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $email = (string) $request->input('email', '');

            if ($email !== '') {
                $statusCode = $response->getStatusCode();

                $this->atlasLogService->registrarConexion($email, $statusCode >= 200 && $statusCode < 400, [
                    'reason' => $statusCode >= 200 && $statusCode < 400 ? 'login_ok' : 'login_error',
                    'http_status' => $statusCode,
                    'user_id' => $request->user()?->id,
                    'rol' => $request->user()?->rol,
                ]);
            }
        } catch (Throwable) {
            // No interrumpir el flujo de autenticación si el log falla.
        }

        return $response;
    }
}