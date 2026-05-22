<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\SesionVideo;
use App\Services\LiveKitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para videollamadas con LiveKit.
 * Genera tokens de acceso a la sala para cliente y profesional.
 * Solo funciona con reservas en modalidad remota.
 */
class VideoController extends Controller
{
    public function __construct(private LiveKitService $liveKit) {}

    /**
     * GET /api/reservas/{reserva}/video-token
     * Devuelve el token LiveKit y la URL para que el usuario se una a la sala.
     * Crea la sesión de video si no existe todavía.
     */
    public function token(Request $request, Reserva $reserva): JsonResponse
    {
        $this->authorize('view', $reserva);

        if ($reserva->modalidad !== 'remota') {
            return response()->json(['error' => 'Esta reserva no es remota.'], 422);
        }

        $estadosValidos = ['confirmada', 'pagada', 'en_curso'];
        if (!in_array($reserva->estado, $estadosValidos)) {
            return response()->json(['error' => 'La reserva no está lista para videollamada.'], 422);
        }

        // Crear sesión de video si no existe
        $sesion = $reserva->sesionVideo ?? SesionVideo::create([
            'reserva_id'  => $reserva->id,
            'nombre_sala' => 'servipro-reserva-' . $reserva->id,
        ]);

        $usuario    = $request->user();
        $identidad  = 'user-' . $usuario->id;
        $token      = $this->liveKit->generarToken($sesion->nombre_sala, $identidad, $usuario->name);

        return response()->json([
            'token'      => $token,
            'ws_url'     => $this->liveKit->wsUrl(),
            'sala'       => $sesion->nombre_sala,
            'identidad'  => $identidad,
        ]);
    }
}
