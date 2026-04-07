<?php

namespace App\Services;

use App\Models\Transaccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class YapeNotificationParser
{
    private string $senderEmail = 'notificaciones@notificacionesbcp.com.pe';

    public function getSenderEmail(): string
    {
        return $this->senderEmail;
    }

    public function parseEmail(string $emailContent): ?array
    {
        $data = [
            'monto' => null,
            'fecha_hora' => null,
            'remitente_nombre' => null,
            'remitente_telefono' => null,
            'numero_operacion' => null,
            'tipo_operacion' => null,
            'canal' => null,
            'origen_cuenta' => null,
            'destino' => null,
            'mensaje' => null,
        ];

        $data['numero_operacion'] = $this->extractNumeroOperacionRaw($emailContent);
        
        if (!$data['numero_operacion']) {
            Log::warning('No se encontro numero de operacion (raw)');
        } else {
            Log::info('Numero de operacion extraido (raw): ' . $data['numero_operacion']);
        }

        $cleanContent = $this->cleanEmailContent($emailContent);

        Log::info('Content limpio (primeros 1000 chars): ' . substr($cleanContent, 0, 1000));

        $data['monto'] = $this->extractMonto($cleanContent);
        $data['fecha_hora'] = $this->extractFechaHora($cleanContent);
        $data['remitente_nombre'] = $this->extractRemitenteNombre($cleanContent);
        $data['remitente_telefono'] = $this->extractRemitenteTelefono($cleanContent);
        $data['tipo_operacion'] = $this->extractTipoOperacion($cleanContent);
        $data['canal'] = $this->extractCanal($cleanContent);
        $data['origen_cuenta'] = $this->extractOrigenCuenta($cleanContent);
        $data['destino'] = $this->extractDestino($cleanContent);
        $data['mensaje'] = $this->extractMensaje($cleanContent);

        Log::info('Datos extraidos: ' . json_encode($data));

        if (!$data['numero_operacion']) {
            Log::warning('No se encontro numero de operacion');
            return null;
        }

        if (!$data['monto']) {
            Log::warning('No se encontro monto');
            return null;
        }

        return $data;
    }

    private function cleanEmailContent(string $content): string
    {
        $content = quoted_printable_decode($content);
        
        $content = preg_replace('/\s+/', ' ', $content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = strip_tags($content);
        $content = str_replace("\n", " ", $content);
        $content = str_replace("\r", " ", $content);
        $content = str_replace("\t", " ", $content);
        $content = preg_replace('/[=]+/', '', $content);
        
        return $content;
    }

    private function extractMonto(string $content): ?float
    {
        if (preg_match('/Monto\s*enviado\s*S\/\s*([\d,]+\.?\d*)/i', $content, $matches)) {
            $monto = str_replace(',', '', $matches[1]);
            return (float) $monto;
        }

        if (preg_match('/S\/\s*([\d,]+\.\d{2})/', $content, $matches)) {
            $monto = str_replace(',', '', $matches[1]);
            return (float) $monto;
        }

        if (preg_match('/Monto[:\s]*S\/\s*([\d,]+\.?\d*)/i', $content, $matches)) {
            $monto = str_replace(',', '', $matches[1]);
            return (float) $monto;
        }

        return null;
    }

    private function extractFechaHora(string $content): ?Carbon
    {
        $patterns = [
            '/(\d{1,2})\s+de\s+(\w+)\s+de\s+(\d{4})\s*[-–—]\s*(\d{1,2}):(\d{2})\s*(AM|PM)?/i',
            '/(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})/i',
            '/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/i',
            '/Fecha[:\s]*(\d{1,2})\s+de\s+(\w+)\s+de\s+(\d{4})\s*[-–—]\s*(\d{1,2}):(\d{2})/i',
        ];

        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                if (count($matches) >= 4) {
                    if (isset($meses[strtolower($matches[2])])) {
                        $dia = (int) $matches[1];
                        $mes = $meses[strtolower($matches[2])];
                        $anio = (int) $matches[3];
                        $hora = (int) $matches[4];
                        $minuto = (int) $matches[5];
                        $ampm = strtoupper($matches[6] ?? 'AM');

                        if ($ampm === 'PM' && $hora < 12) {
                            $hora += 12;
                        } elseif ($ampm === 'AM' && $hora === 12) {
                            $hora = 0;
                        }

                        return Carbon::create($anio, $mes, $dia, $hora, $minuto);
                    }
                }
            }
        }

        return null;
    }

    private function extractRemitenteNombre(string $content): ?string
    {
        if (preg_match('/Enviado a\s+([A-Za-zÀ-ÿ\s]+?)(?:\s+\*|$)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Recibiste\s+de\s+([A-Za-zÀ-ÿ\s]+?)(?:\s+\*|$)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Destinatario[:\s]*([A-Za-zÀ-ÿ\s]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractRemitenteTelefono(string $content): ?string
    {
        if (preg_match('/Enviado a\s+[A-Za-zÀ-ÿ\s]+\s+(\*{4,}\s*\d+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/(\*{4,}\s*\d{3,})/', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractNumeroOperacion(string $content): ?string
    {
        if (preg_match('/N(?:úU)mero\s+de\s+operaci(?:óO)n\s+(\d{6,})/i', $content, $matches)) {
            return $matches[1];
        }

        if (preg_match('/operaci[^\s]*n\s+(\d{6,})/i', $content, $matches)) {
            return $matches[1];
        }

        if (preg_match('/canal.*?(\d{8})/i', $content, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(\d{8})(?:\s|$)/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractNumeroOperacionRaw(string $content): ?string
    {
        $decoded = quoted_printable_decode($content);
        
        if (preg_match('/N(?:úU)mero\s+de\s+operaci(?:óO)n\s+(\d{6,})/i', $decoded, $matches)) {
            return $matches[1];
        }

        if (preg_match('/operaci[^\s]*n\s+(\d{6,})/i', $decoded, $matches)) {
            return $matches[1];
        }

        if (preg_match('/canal\s*:\s*([A-Za-z0-9\s]+?)(?=\s*N[úU]mero)/im', $decoded, $matches)) {
            if (preg_match('/(\d{6,})/', $matches[1], $numMatch)) {
                return $numMatch[1];
            }
        }

        if (preg_match('/N=C3=BAmero\s+de\s+operaci=C3=B3n\s+(\d{6,})/i', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractCanal(string $content): ?string
    {
        if (preg_match('/Canal\s*:\s*([A-Za-z0-9\s]+?)(?=\s*N=C3|=C3|Baja|Calma|Enviado|Monto|Fecha|Número)/im', $content, $matches)) {
            $canal = trim($matches[1]);
            if (strlen($canal) < 50) {
                return $canal;
            }
        }

        if (preg_match('/Banca\s*(M[óO]vil|Internet|Web)/i', $content, $matches)) {
            return $matches[0];
        }

        if (preg_match('/(APP|Yape|Cajero)/i', $content, $matches)) {
            return $matches[0];
        }

        return 'Banca Móvil BCP';
    }

    private function extractTipoOperacion(string $content): ?string
    {
        if (preg_match('/Operación\s+realizada\s+([^\n]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Tipo\s+de\s+operación[:\s]*([^\n]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractOrigenCuenta(string $content): ?string
    {
        if (preg_match('/Desde[:\s]*([^\n]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Origen[:\s]*([^\n]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractDestino(string $content): ?string
    {
        if (preg_match('/Destino[:\s]*([^\n]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractMensaje(string $content): ?string
    {
        if (preg_match('/Mensaje[:\s]*([^\n]+)/i', $content, $matches)) {
            $msg = trim($matches[1]);
            return $msg ?: null;
        }

        return null;
    }

    public function isYapeNotification(string $emailContent): bool
    {
        $contentLower = strtolower($emailContent);
        
        $indicators = [
            'notificacionesbcp',
            'yapeo',
            'yape',
            'operación realizada',
            'monto enviado',
            'número de operación',
            'cuenta de ahorro',
        ];

        $count = 0;
        foreach ($indicators as $indicator) {
            if (strpos($contentLower, $indicator) !== false) {
                $count++;
            }
        }

        return $count >= 2;
    }

    public function existsOperation(string $numeroOperacion): bool
    {
        return Transaccion::where('nro_operacion', $numeroOperacion)->exists();
    }
}