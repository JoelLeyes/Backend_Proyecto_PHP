<?php

namespace App\Services;

/**
 * Genera tokens de acceso para LiveKit usando JWT firmado con HS256.
 * Los tokens permiten a un usuario unirse a una sala de videollamada.
 */
class LiveKitService
{
    private string $apiKey;
    private string $apiSecret;
    private string $wsUrl;

    public function __construct()
    {
        $this->apiKey    = config('services.livekit.key', '');
        $this->apiSecret = config('services.livekit.secret', '');
        $this->wsUrl     = config('services.livekit.url', '');
    }

    /**
     * Genera un token JWT para que un participante se una a una sala.
     *
     * @param  string  $sala       Nombre único de la sala (ej: "sala-reserva-42")
     * @param  string  $identidad  ID único del participante (ej: "user-7")
     * @param  string  $nombre     Nombre a mostrar en la videollamada
     * @param  int     $ttl        Tiempo de vida del token en segundos (default: 4 horas)
     */
    public function generarToken(string $sala, string $identidad, string $nombre, int $ttl = 14400): string
    {
        $ahora = time();

        $payload = [
            'iss'  => $this->apiKey,
            'sub'  => $identidad,
            'iat'  => $ahora,
            'exp'  => $ahora + $ttl,
            'name' => $nombre,
            'video' => [
                'room'         => $sala,
                'roomJoin'     => true,
                'canPublish'   => true,
                'canSubscribe' => true,
            ],
        ];

        return $this->firmarJwt($payload);
    }

    public function wsUrl(): string
    {
        return $this->wsUrl;
    }

    // ── JWT HS256 manual (no requiere dependencias externas) ──────────────────

    private function firmarJwt(array $payload): string
    {
        $header  = $this->base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $cuerpo  = $this->base64url(json_encode($payload));
        $firma   = $this->base64url(hash_hmac('sha256', "{$header}.{$cuerpo}", $this->apiSecret, true));

        return "{$header}.{$cuerpo}.{$firma}";
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
