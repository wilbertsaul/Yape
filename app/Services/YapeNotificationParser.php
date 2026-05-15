<?php

namespace App\Services;

use App\Models\Transaccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class YapeNotificationParser
{
    private array $senderEmails = [
        'notificaciones@notificacionesbcp.com.pe',
        'notificaciones@yape.pe',
    ];

    public function getSenderEmails(): array
    {
        return $this->senderEmails;
    }

    public function getSenderEmail(): string
    {
        return $this->senderEmails[0];
    }

    private function isYapeAppEmail(string $content): bool
    {
        if (stripos($content, '@yape.pe') !== false) {
            return true;
        }
        return stripos($content, 'acabas de yapear') !== false
            || stripos($content, 'has recibido un yapeo') !== false
            || stripos($content, 'nombre del beneficiario') !== false
            || stripos($content, 'nombre del yapero') !== false;
    }

    public function parseEmail(string $emailContent): ?array
    {
        if ($this->isYapeAppEmail($emailContent)) {
            return $this->parseYapeAppEmail($emailContent);
        }

        $data = [
            'monto' => null,
            'fecha_hora' => null,
            'remitente_nombre' => null,
            'remitente_telefono' => null,
            'numero_operacion' => null,
            'tipo_operacion' => null,
            'tipo_movimiento' => null,
            'canal' => null,
            'origen_cuenta' => null,
            'destino' => null,
            'mensaje' => null,
        ];

        $subject = $this->extractSubject($emailContent);
        $baseType = $this->classifyBySubject($subject);
        $subjectLower = $subject ? mb_strtolower($subject) : '';

        $data['numero_operacion'] = $this->extractNumeroOperacionRaw($emailContent);

        $cleanContent = $this->cleanEmailContent($emailContent);

        if (!$data['numero_operacion']) {
            $data['numero_operacion'] = $this->extractNumeroOperacion($cleanContent);
        }

        $data['monto'] = $this->extractMonto($cleanContent);
        $data['fecha_hora'] = $this->extractFechaHora($cleanContent);
        $data['tipo_operacion'] = $this->extractTipoOperacion($cleanContent);

        if (str_contains($subjectLower, 'yape') || $this->isYapeNotification($emailContent)) {
            $data['tipo_movimiento'] = $this->determineYapeDirection($emailContent);
            $data['remitente_nombre'] = $this->extractRemitenteNombre($cleanContent);
            $data['remitente_telefono'] = $this->extractRemitenteTelefono($cleanContent);
            $data['canal'] = $this->extractCanal($cleanContent);
            $data['destino'] = $this->extractDestino($cleanContent);
            $data['mensaje'] = $this->extractMensaje($cleanContent);
        } else {
            $data['tipo_movimiento'] = $baseType;
            $data['remitente_nombre'] = $this->extractDestinatarioGeneral($cleanContent);
            $data['origen_cuenta'] = $this->extractOrigenCuenta($cleanContent);
            $data['destino'] = $this->extractDestino($cleanContent);
            $data['mensaje'] = $this->extractMensaje($cleanContent);
        }

        $tipoOp = $data['tipo_operacion'] ?? '';
        if (stripos($tipoOp, 'transferencia a terceros') !== false) {
            $data['tipo_movimiento'] = 'transferencia_terceros';
        }

        if (!$data['monto']) {
            return null;
        }

        return $data;
    }

    private function parseYapeAppEmail(string $emailContent): ?array
    {
        $data = [
            'monto' => null,
            'fecha_hora' => null,
            'remitente_nombre' => null,
            'remitente_telefono' => null,
            'numero_operacion' => null,
            'tipo_operacion' => null,
            'tipo_movimiento' => null,
            'canal' => 'Yape App',
            'origen_cuenta' => null,
            'destino' => null,
            'mensaje' => null,
        ];

        $subject = $this->extractSubject($emailContent);
        $subjectLower = $subject ? mb_strtolower($subject) : '';

        // Skip non-transactional Yape App notifications
        if (stripos($subjectLower, 'recarga') !== false) return null;
        if (stripos($subjectLower, 'bienvenida') !== false) return null;
        if (stripos($subjectLower, 'cambios en monto') !== false) return null;
        if (stripos($subjectLower, 'servicio') !== false) return null;

        $cleanContent = $this->cleanEmailContent($emailContent);

        // Extract amount from "Monto de yapeo" or "Monto de Yapeo" field
        if (preg_match('/Monto de yapeo\*?[:\s]*S\/\s*([\d,]+\.?\d*)/i', $cleanContent, $matches)) {
            $data['monto'] = (float) str_replace(',', '', $matches[1]);
        }

        if ($data['monto'] === null && preg_match('/\bS\/\s*([\d,]+\.?\d*)\b.*?(?:yapeo|beneficiario)/is', $cleanContent, $matches)) {
            $data['monto'] = (float) str_replace(',', '', $matches[1]);
        }

        if ($data['monto'] === null) {
            $dateMatch = $this->extractMonto($cleanContent);
            if ($dateMatch !== null) {
                $data['monto'] = $dateMatch;
            }
        }

        // Determine movement type based on content
        $isEnviado = stripos($cleanContent, 'acabas de yapear') !== false
            || stripos($cleanContent, 'yapeaste') !== false;

        if ($isEnviado) {
            $data['tipo_movimiento'] = 'yape_enviado';

            if (preg_match('/Nombre del Beneficiario[:\s]*([A-Za-zÀ-ÿÑñ\s\.\*]+?)(?:\n|$)/i', $cleanContent, $matches)) {
                $data['remitente_nombre'] = trim(rtrim($matches[1], '* '));
            }

            if (preg_match('/Celular del Beneficiario[:\s]*(\*{2,}\d{2,})/i', $cleanContent, $matches)) {
                $data['destino'] = trim($matches[1]);
            }
        } elseif (stripos($cleanContent, 'has recibido un yapeo') !== false) {
            $data['tipo_movimiento'] = 'yape_recibido';

            if (preg_match('/Nombre del Yapero[:\s]*([A-Za-zÀ-ÿÑñ\s\.\*]+?)(?:\n|$)/i', $cleanContent, $matches)) {
                $data['remitente_nombre'] = trim(rtrim($matches[1], '* '));
            }
        } else {
            return null;
        }

        // Extract operation number (handles both "N° de operación 12345" and "Nº de operación Yape: 12345")
        if (preg_match('/N[º°]\s*de\s*operaci[óo]n\s*(?:Yape)?\s*[:\s]*(\d+)/i', $cleanContent, $matches)) {
            $data['numero_operacion'] = $matches[1];
        }

        $data['fecha_hora'] = $this->extractFechaHora($cleanContent);

        if (!$data['monto']) {
            return null;
        }

        return $data;
    }

    private function extractSubject(string $content): ?string
    {
        if (preg_match('/^Subject:\s*(.+)/im', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private function classifyBySubject(?string $subject): string
    {
        if (!$subject) {
            return 'yape_enviado';
        }

        $subjectLower = mb_strtolower($subject);

        if (str_contains($subjectLower, 'entre mis cuentas') || str_contains($subjectLower, 'entre cuentas')) {
            return 'traslado_fondos';
        }

        return 'yape_enviado';
    }

    public function determineYapeDirection(string $emailContent): string
    {
        $contentLower = strtolower($emailContent);

        if (str_contains($contentLower, 'recibiste')) {
            return 'yape_recibido';
        }

        return 'yape_enviado';
    }

    private function cleanEmailContent(string $content): string
    {
        $body = $this->extractBody($content);

        $body = quoted_printable_decode($body);

        // Convert from Latin-1 (ISO-8859-1) to UTF-8 if needed
        if (!mb_check_encoding($body, 'UTF-8')) {
            $body = mb_convert_encoding($body, 'UTF-8', 'ISO-8859-1');
        }

        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $body = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $body);
        $body = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $body);

        $body = strip_tags($body);

        $body = preg_replace('/[=]+/', '', $body);

        $body = preg_replace('/\r\n/', "\n", $body);
        $body = preg_replace('/\r/', "\n", $body);

        $body = preg_replace('/[ \t]+/', ' ', $body);

        $body = preg_replace('/^[ \t]+/m', '', $body);
        $body = preg_replace('/[ \t]+$/m', '', $body);

        $body = preg_replace('/\n{3,}/', "\n\n", $body);

        $body = preg_replace('/\xC2\xA0/', ' ', $body);

        $lines = explode("\n", $body);
        $start = 0;
        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if ($trimmed !== '' && preg_match('/[A-Za-zÀ-ÿ]/u', $trimmed)) {
                $start = $i;
                break;
            }
        }
        $body = implode("\n", array_slice($lines, $start));

        return trim($body);
    }

    private function extractBody(string $content): string
    {
        $idx = strpos($content, "\r\n\r\n");
        if ($idx === false) {
            $idx = strpos($content, "\n\n");
        }
        if ($idx !== false) {
            $body = substr($content, $idx + 4);
            if (strlen($body) < 100) {
                $idx = strpos($content, "\n\n", $idx + 4);
                if ($idx !== false) {
                    $body = substr($content, $idx + 2);
                }
            }
            return $body;
        }
        return $content;
    }

    private function extractMonto(string $content): ?float
    {
        if (preg_match('/Monto\s+enviado\s*\n\s*S\/\s*([\d,]+\.?\d*)/i', $content, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }

        if (preg_match('/Monto\s*enviado\s*S\/\s*([\d,]+\.?\d*)/iu', $content, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }

        if (preg_match('/S\/\s*([\d,]+\.\d{2})/', $content, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }

        return null;
    }

    private function extractFechaHora(string $content): ?Carbon
    {
        if (preg_match('/Fecha\s+y\s+hora\s*\n\s*(\d{1,2})\s+de\s+(\w+)\s+de\s+(\d{4})\s*[-–—]\s*(\d{1,2}):(\d{2})\s*(AM|PM)?/i', $content, $matches)) {
            return $this->parseDate($matches);
        }

        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?/i', $content, $matches)) {
            $dia = (int)$matches[1];
            $mes = (int)$matches[2];
            $anio = (int)$matches[3];
            $hora = (int)$matches[4];
            $minuto = (int)$matches[5];
            $segundo = isset($matches[6]) ? (int)$matches[6] : 0;
            if ($mes >= 1 && $mes <= 12 && $dia >= 1 && $dia <= 31) {
                return Carbon::create($anio, $mes, $dia, $hora, $minuto, $segundo);
            }
        }

        // Normalize Peruvian 12-hour format (a. m. / p. m. → AM / PM)
        $content = str_ireplace(['a. m.', 'p. m.', 'a.m.', 'p.m.'], ['AM', 'PM', 'AM', 'PM'], $content);

        $patterns = [
            '/(\d{1,2})\s+de\s+(\w+)\s+de\s+(\d{4})\s*[-–—]\s*(\d{1,2}):(\d{2})\s*(AM|PM)?/i',
            '/(\d{1,2})\s+(\w+)\s+(\d{4})\s*[-–—]\s*(\d{1,2}):(\d{2})\s*(AM|PM)?/i',
            '/[A-Za-zÀ-ÿ]+,\s*(\d{1,2})\s+(\w+)\s+(\d{4})\s*[-–—]\s*(\d{1,2}):(\d{2})\s*(AM|PM)/i',
            '/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                if (count($matches) >= 4 && isset($matches[2])) {
                    $meses = [
                        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
                        'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
                        'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
                    ];
                    if (isset($meses[strtolower($matches[2])])) {
                        return $this->parseDate($matches);
                    }
                }
            }
        }

        return null;
    }

    private function parseDate(array $matches): Carbon
    {
        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
        ];

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

    private function extractRemitenteNombre(string $content): ?string
    {
        if (preg_match('/Recibiste\s+un\s+yapeo\s+de\s+S\/\s*[\d,]+\.?\d*\s+de\s+([A-Za-zÀ-ÿÑñ\s\.]+?)(?:\n|$)/i', $content, $matches)) {
            return trim(rtrim($matches[1], '.'));
        }

        if (preg_match('/Enviado\s+a\s*\n\s*([A-Za-zÀ-ÿÑñ\s\.]+?)(?:\s*\*|$)/im', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Enviado\s+por\s*\n\s*([A-Za-zÀ-ÿÑñ\s\.]+?)(?:\s*\*|$)/im', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Recibiste\s+de\s*\n\s*([A-Za-zÀ-ÿÑñ\s\.]+?)(?:\s*\*|$)/im', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Enviado a\s+([A-Za-zÀ-ÿÑñ\s\.]+?)(?:\s*\*|$)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Enviado por\s+([A-Za-zÀ-ÿÑñ\s\.]+?)(?:\s*\*|$)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Recibiste\s+de\s+([A-Za-zÀ-ÿÑñ\s\.]+?)(?:\s*\*|$)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Destinatario[:\s]*([A-Za-zÀ-ÿÑñ\s]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractRemitenteTelefono(string $content): ?string
    {
        if (preg_match('/Enviado\s+a\s*\n\s*([^\n]+)/i', $content, $matches)) {
            $line = $matches[1];
            if (preg_match('/(\*{2,}\S*(?:\s+\S+)*)/', $line, $m)) {
                return trim($m[1]);
            }
        }

        if (preg_match('/Enviado\s+a\s+([A-Za-zÀ-ÿ\s\.]+\s+\*{2,}\S*(?:\s+\S+)*)/i', $content, $matches)) {
            if (preg_match('/(\*{2,}\S*(?:\s+\S+)*)/', $matches[1], $m)) {
                return trim($m[1]);
            }
        }

        if (preg_match('/(\*{4,}\s*\d{3,})/', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractNumeroOperacion(string $content): ?string
    {
        if (preg_match('/N(?:ú|u)mero\s+de\s+operaci(?:ó|o)n\s*\n\s*(\d{6,})/i', $content, $matches)) {
            return $matches[1];
        }

        if (preg_match('/N(?:ú|u)mero\s+de\s+operaci(?:ó|o)n\s+(\d{6,})/i', $content, $matches)) {
            return $matches[1];
        }

        if (preg_match('/operaci[^\s]*n\s+(\d{6,})/i', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractNumeroOperacionRaw(string $content): ?string
    {
        $decoded = quoted_printable_decode($content);

        if (preg_match('/N(?:ú|u)mero\s+de\s+operaci(?:ó|o)n\s+(\d{6,})/i', $decoded, $matches)) {
            return $matches[1];
        }

        if (preg_match('/operaci[^\s]*n\s+(\d{6,})/i', $decoded, $matches)) {
            return $matches[1];
        }

        if (preg_match('/N=C3=BAmero\s+de\s+operaci=C3=B3n\s+(\d{6,})/i', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractCanal(string $content): ?string
    {
        if (preg_match('/Canal\s*\n\s*(.+)/i', $content, $matches)) {
            $canal = trim($matches[1]);
            if (strlen($canal) < 50) {
                return $canal;
            }
        }

        if (preg_match('/Canal\s*:\s*([A-Za-z0-9\s]+?)(?=[A-Z]|$)/im', $content, $matches)) {
            $canal = trim($matches[1]);
            if (strlen($canal) < 50) {
                return $canal;
            }
        }

        if (preg_match('/Banca\s*(M[óo]vil|Internet|Web)/i', $content, $matches)) {
            return $matches[0];
        }

        if (preg_match('/(APP|Yape|Cajero)/i', $content, $matches)) {
            return $matches[0];
        }

        return 'Banca Móvil BCP';
    }

    private function extractTipoOperacion(string $content): ?string
    {
        if (preg_match('/Operaci[óo]n\s+realizada\s*\n\s*(.+?)(?:\n|\s+(?:Fecha|Monto|Enviado|Destino|Desde|Canal|N[uú]mero))/iu', $content, $matches)) {
            $tipo = trim($matches[1]);
            $tipo = preg_replace('/\s+/', ' ', $tipo);
            return $tipo;
        }

        if (preg_match('/Operaci[óo]n\s+realizada\s+(.+?)(?:\s+(?:Fecha|Monto|Enviado|Destino|Desde|Canal|N[uú]mero))/iu', $content, $matches)) {
            $tipo = trim($matches[1]);
            $tipo = preg_replace('/\s+/', ' ', $tipo);
            return $tipo;
        }

        if (preg_match('/Tipo\s+de\s+operaci[óo]n[:\s]*(.+)/iu', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractOrigenCuenta(string $content): ?string
    {
        if (preg_match('/Desde\s*\n\s*(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

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
        if (preg_match('/^Destino\s*\n\s*(.+)/im', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Destino[:\s]*([^\n]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^Hacia\s*\n\s*(.+)/im', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Hacia[:\s]*([^\n]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractDestinatarioGeneral(string $content): ?string
    {
        if (preg_match('/Beneficiario[:\s]*([A-Za-zÀ-ÿÑñ\s\.]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Empresa[:\s]*([A-Za-zÀ-ÿÑñ\s\.\-]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Destinatario[:\s]*([A-Za-zÀ-ÿÑñ\s]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/A nombre de[:\s]*([A-Za-zÀ-ÿÑñ\s\.]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Enviado a\s+([A-Za-zÀ-ÿÑñ\s\.]+?)(?:\s*\*|$)/i', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Enviado a\s*\n\s*([A-Za-zÀ-ÿÑñ\s\.]+?)(?:\s*\*|$)/im', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractMensaje(string $content): ?string
    {
        if (preg_match('/^Mensaje\s*\n\s*\n\s*Canal/im', $content)) {
            return null;
        }

        if (preg_match('/^Mensaje\s*\n\s*\n\s*(.+?)\s*\n\s*(?:Canal|N[uú]mero)\s*\n/imu', $content, $matches)) {
            $msg = trim($matches[1]);
            return $msg ?: null;
        }

        if (preg_match('/^Mensaje\s*\n\s*(.+?)\s*\n\s*(?:Canal|N[uú]mero)\s*\n/imu', $content, $matches)) {
            $msg = trim($matches[1]);
            return $msg ?: null;
        }

        if (preg_match('/Mensaje[:\s]*([A-Za-zÀ-ÿ0-9]+?)(?:\s+(?:Canal|N[uú]mero)|$)/iu', $content, $matches)) {
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
        if (empty($numeroOperacion)) return false;
        return Transaccion::where('nro_operacion', $numeroOperacion)->exists();
    }
}
