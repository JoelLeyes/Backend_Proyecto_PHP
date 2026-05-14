<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Integración con PayPal REST API v2 (sandbox / live).
 * Solo maneja la comunicación de red — nunca toca la base de datos.
 */
class PaypalService
{
    private string $clientId;
    private string $secret;
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId = config('services.paypal.client_id', '');
        $this->secret   = config('services.paypal.secret', '');
        $mode           = config('services.paypal.mode', 'sandbox');
        $this->baseUrl  = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function accessToken(): string
    {
        $response = Http::withBasicAuth($this->clientId, $this->secret)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

        if (!$response->successful()) {
            throw new \RuntimeException('PayPal auth error: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Crea una orden de pago. Devuelve el PayPal order_id para el SDK del frontend.
     */
    public function crearOrden(float $monto, string $moneda, string $descripcion): string
    {
        $token    = $this->accessToken();
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/v2/checkout/orders", [
                'intent'         => 'CAPTURE',
                'purchase_units' => [[
                    'amount'      => [
                        'currency_code' => $moneda,
                        'value'         => number_format($monto, 2, '.', ''),
                    ],
                    'description' => mb_substr($descripcion, 0, 127),
                ]],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('PayPal create order error: ' . $response->body());
        }

        return $response->json('id');
    }

    /**
     * Captura una orden aprobada por el usuario. Devuelve el array completo de PayPal.
     */
    public function capturarOrden(string $orderId): array
    {
        $token    = $this->accessToken();
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

        if (!$response->successful()) {
            throw new \RuntimeException('PayPal capture error: ' . $response->body());
        }

        return $response->json();
    }
}
