<?php

namespace App\Services;

use App\Models\Transaccion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApisunatService
{
    protected string $endpoint;
    protected string $token;
    protected array $emisor;

    public function __construct()
    {
        $sandbox = config('api-sunat.ambiente') === 'sandbox';
        $this->endpoint = $sandbox
            ? config('api-sunat.endpoint_sandbox')
            : config('api-sunat.endpoint_produccion');
        $this->token = config('api-sunat.token');
        $this->emisor = config('api-sunat.emisor');
    }

    public function emitirBoleta(Transaccion $transaccion, int $correlativo, string $serie = null): array
    {
        $serie = $serie ?? config('api-sunat.serie', 'EB01');
        $payload = $this->buildPayload($transaccion, $correlativo, $serie);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Log::info('Apisunat request', ['payload' => $payload]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ])->withBody($json, 'application/json')->post($this->endpoint);

            $body = $response->json() ?? [];
            $statusCode = $response->status();

            Log::info('Apisunat response', [
                'status' => $statusCode,
                'body' => $body,
                'transaccion_id' => $transaccion->id,
            ]);

            if ($response->successful() && ($body['success'] ?? false)) {
                $payloadData = $body['payload'] ?? [];

                return [
                    'success' => true,
                    'serie' => $serie,
                    'numero' => $correlativo,
                    'xml_url' => $payloadData['xml'] ?? null,
                    'pdf_url' => $payloadData['pdf']['ticket'] ?? $payloadData['pdf'] ?? null,
                    'cdr_url' => $payloadData['cdr'] ?? null,
                    'hash' => $payloadData['hash'] ?? null,
                    'estado_sunat' => $payloadData['estado'] ?? 'PENDIENTE',
                    'respuesta_raw' => $body,
                    'mensaje' => $body['message'] ?? 'Boleta emitida correctamente',
                ];
            }

            $errorMsg = $body['message'] ?? 'Error desconocido';

            return [
                'success' => false,
                'mensaje' => 'Error API SUNAT: ' . $errorMsg,
                'respuesta_raw' => $body,
            ];
        } catch (\Exception $e) {
            Log::error('Apisunat exception', [
                'error' => $e->getMessage(),
                'transaccion_id' => $transaccion->id,
            ]);

            return [
                'success' => false,
                'mensaje' => 'Error de conexión: ' . $e->getMessage(),
                'respuesta_raw' => null,
            ];
        }
    }

    public function buildPayload(Transaccion $transaccion, int $correlativo, string $serie): array
    {
        $monto = (float) $transaccion->monto_transferencia;
        $cliente = $transaccion->cliente;

        return [
            'documento' => 'boleta',
            'serie' => $serie,
            'numero' => $correlativo,
            'fecha_de_emision' => now()->format('Y-m-d'),
            'hora_de_emision' => now()->format('H:i:s'),
            'moneda' => 'PEN',
            'tipo_operacion' => '0101',

            'cliente_tipo_de_documento' => '1',
            'cliente_numero_de_documento' => $cliente?->dni ?? '-',
            'cliente_denominacion' => $cliente?->nombre_completo ?? $transaccion->remitente_nombre ?? 'CONSUMIDOR FINAL',
            'cliente_direccion' => '-',

            'items' => [[
                'unidad_de_medida' => 'ZZ',
                'descripcion' => 'Operacion Yape',
                'cantidad' => '1',
                'valor_unitario' => (string) $monto,
                'precio_unitario' => (string) $monto,
                'porcentaje_igv' => '0',
                'codigo_tipo_afectacion_igv' => '20',
                'nombre_tributo' => 'GRA',
            ]],

            'total' => '0',
        ];
    }
}
