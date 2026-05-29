<?php

namespace App\Services;

use App\Models\Transaccion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacturaGoService
{
    protected string $endpoint;
    protected string $token;
    protected array $emisor;

    public function __construct()
    {
        $demo = config('facturago.ambiente') === 'D';
        $this->endpoint = $demo
            ? config('facturago.endpoint_demo')
            : config('facturago.endpoint_produccion');
        $this->token = $demo
            ? config('facturago.token_demo')
            : config('facturago.token_produccion');
        $this->emisor = config('facturago.emisor');
    }

    public function emitirBoleta(Transaccion $transaccion, int $correlativo, string $serie = null): array
    {
        $serie = $serie ?? config('facturago.serie', 'EB01');
        $payload = $this->buildPayload($transaccion, $correlativo, $serie);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token token="' . $this->token . '"',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->endpoint, $payload);

            $body = $response->json() ?? [];
            $statusCode = $response->status();

            if ($response->successful() && ($body['success'] ?? false)) {
                return [
                    'success' => true,
                    'serie' => $serie,
                    'numero' => $correlativo,
                    'xml_url' => $body['xml'] ?? $body['xml_url'] ?? null,
                    'pdf_url' => $body['pdf'] ?? $body['pdf_url'] ?? null,
                    'cdr_url' => $body['cdr'] ?? $body['cdr_url'] ?? null,
                    'hash' => $body['hash'] ?? null,
                    'estado_sunat' => $body['estado'] ?? $body['estado_sunat'] ?? 'PENDIENTE',
                    'respuesta_raw' => $body,
                    'mensaje' => $body['message'] ?? 'Boleta emitida correctamente',
                ];
            }

            $errorMsg = $body['message'] ?? $body['error'] ?? 'Error desconocido';
            Log::warning('FacturaGo error', [
                'status' => $statusCode,
                'response' => $body,
                'transaccion_id' => $transaccion->id,
            ]);

            return [
                'success' => false,
                'mensaje' => 'Error FacturaGo: ' . $errorMsg,
                'respuesta_raw' => $body,
            ];
        } catch (\Exception $e) {
            Log::error('FacturaGo exception', [
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
            'AMBIENTE' => config('facturago.ambiente', 'D'),
            'TIPO_CPE' => 'BO',
            'FORMA_PAGO' => 'Contado',
            'TIPO_OPERACION' => '0101',

            'SERIE' => $serie,
            'CORRELATIVO' => $correlativo,
            'FECHA_EMISION' => now()->format('Y-m-d'),
            'HORA_EMISION' => now()->format('H:i:s'),
            'CODIGO_MONEDA' => 'PEN',

            'TOTAL_GRAVADA' => 0,
            'TOTAL_EXONERADA' => $monto,
            'SUB_TOTAL' => $monto,
            'PORCENTAJE_IGV' => 0,
            'TOTAL_IGV' => 0,
            'TOTAL' => $monto,
            'TOTAL_LETRAS' => NumeroALetras::convertir($monto),

            'CLIENTE' => [
                'TIPO_DOCUMENTO_IDENTIDAD' => 1,
                'NUMERO_DOCUMENTO_IDENTIDAD' => $cliente?->dni ?? '-',
                'RAZON_SOCIAL' => $cliente?->nombre_completo ?? $transaccion->remitente_nombre ?? 'CONSUMIDOR FINAL',
                'DIRECCION' => '-',
                'UBIGEO' => '-',
            ],

            'EMISOR' => [
                'TIPO_DOCUMENTO_IDENTIDAD' => 6,
                'NUMERO_DOCUMENTO_IDENTIDAD' => $this->emisor['ruc'] ?? '',
                'RAZON_SOCIAL' => $this->emisor['razon_social'] ?? '',
                'DIRECCION' => $this->emisor['direccion'] ?? '',
                'UBIGEO' => $this->emisor['ubigeo'] ?? '',
                'USUARIO_SOL' => $this->emisor['usuario_sol'] ?? '',
                'CLAVE_SOL' => $this->emisor['clave_sol'] ?? '',
            ],

            'DETALLE' => [[
                'ITEM' => 1,
                'DESCRIPCION' => 'Operacion Yape',
                'UNIDAD_MEDIDA' => 'ZZ',
                'CANTIDAD' => 1,
                'VALOR_UNITARIO' => $monto,
                'PRECIO_UNITARIO' => $monto,
                'VALOR_ITEM' => $monto,
                'TOTAL_IGV' => 0,
                'PRECIO_TIPO_CODIGO' => '01',
                'TOTAL_IMPUESTOS' => 0,
                'TIPO_IGV' => 20,
            ]],
        ];
    }
}
