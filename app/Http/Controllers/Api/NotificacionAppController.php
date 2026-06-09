<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificacionApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestiona el historial persistente de notificaciones de la campana.
 */
class NotificacionAppController extends Controller
{
    /**
     * GET /api/notificaciones
     * Devuelve las últimas 50 notificaciones del usuario autenticado, de más reciente a más antigua.
     */
    public function index(Request $request): JsonResponse
    {
        $notificaciones = NotificacionApp::where('usuario_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($notificaciones);
    }

    /**
     * POST /api/notificaciones/leer-todas
     * Marca todas las notificaciones del usuario como leídas.
     */
    public function leerTodas(Request $request): JsonResponse
    {
        NotificacionApp::where('usuario_id', $request->user()->id)
            ->where('leida', false)
            ->update(['leida' => true]);

        return response()->json(['mensaje' => 'Notificaciones marcadas como leídas.']);
    }
}
