<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckCertSunat extends Command
{
    protected $signature = 'sunat:check-cert';
    protected $description = 'Verifica el certificado digital SUNAT';

    public function handle(): int
    {
        $path = config('sunat.certificate_path');
        $password = config('sunat.certificate_password');

        $this->info("Ruta: $path");
        $this->info("Password: " . str_repeat('*', strlen($password)));

        if (!file_exists($path)) {
            $this->error('Certificado no encontrado en: ' . $path);
            return 1;
        }

        $this->info('Archivo encontrado. Size: ' . filesize($path) . ' bytes');
        $this->line('');

        try {
            $pemContent = app(\App\Services\Sunat\CertificadoService::class)->getPem();
            $this->info('✅ Certificado PEM cargado exitosamente');
        } catch (\Throwable $e) {
            $this->error('Error al cargar certificado: ' . $e->getMessage());
            return 1;
        }

        if (!openssl_x509_read($pemContent)) {
            $this->error('El contenido PEM no es un certificado X.509 válido');
            while ($msg = openssl_error_string()) {
                $this->error('  OpenSSL: ' . $msg);
            }
            return 1;
        }

        $certData = openssl_x509_parse($pemContent);

        if (!$certData) {
            $this->error('No se pudo parsear el certificado');
            return 1;
        }

        $this->info('');
        $this->info('=== Datos del Certificado ===');

        $subject = $certData['subject'] ?? [];
        $this->line('Subject:');
        foreach (['CN', 'O', 'OU', 'L', 'ST', 'C', 'E'] as $k) {
            if (isset($subject[$k])) {
                $val = is_array($subject[$k]) ? implode(', ', $subject[$k]) : $subject[$k];
                $this->line("  $k: $val");
            }
        }

        $issuer = $certData['issuer'] ?? [];
        $this->line('Issuer:');
        foreach (['CN', 'O', 'OU', 'C'] as $k) {
            if (isset($issuer[$k])) {
                $val = is_array($issuer[$k]) ? implode(', ', $issuer[$k]) : $issuer[$k];
                $this->line("  $k: $val");
            }
        }

        $this->line('Serial: ' . ($certData['serialNumber'] ?? 'N/A'));

        $validFrom = date('Y-m-d H:i:s', $certData['validFrom_time_t'] ?? 0);
        $validTo = date('Y-m-d H:i:s', $certData['validTo_time_t'] ?? 0);
        $now = time();

        $this->line('Válido desde: ' . $validFrom);
        $this->line('Válido hasta: ' . $validTo);

        if ($certData['validTo_time_t'] < $now) {
            $this->error('❌ Certificado VENCIDO');
        } elseif ($certData['validFrom_time_t'] > $now) {
            $this->warn('⚠️ Certificado aún no es válido (futuro)');
        } else {
            $this->info('✅ Certificado vigente');
        }

        $rucEnCert = '';
        if (!empty($subject['serialNumber'])) {
            $rucEnCert = is_array($subject['serialNumber']) ? implode(', ', $subject['serialNumber']) : $subject['serialNumber'];
        } elseif (!empty($subject['OU'])) {
            $ou = is_array($subject['OU']) ? implode(', ', $subject['OU']) : $subject['OU'];
            $rucEnCert = $ou;
        }
        $rucConfig = config('sunat.ruc');
        $this->line('');
        $this->line("RUC en certificado: $rucEnCert");
        $this->line("RUC en config:     $rucConfig");
        if (strpos($rucEnCert, $rucConfig) !== false || strpos($rucConfig, $rucEnCert) !== false) {
            $this->info('✅ RUC coincide');
        } else {
            $this->warn('⚠️ RUC no coincide exactamente');
        }

        $fingerprint = openssl_x509_fingerprint($pemContent);
        if ($fingerprint) {
            $this->line('Fingerprint (SHA1): ' . implode(':', str_split($fingerprint, 2)));
        }

        $fingerprint256 = openssl_x509_fingerprint($pemContent, 'sha256');
        if ($fingerprint256) {
            $this->line('Fingerprint (SHA256): ' . implode(':', str_split($fingerprint256, 2)));
        }

        $this->line('Versión: ' . ($certData['version'] ?? 'N/A'));
        $this->line('Algoritmo de firma: ' . ($certData['signatureTypeLN'] ?? 'N/A'));

        $this->line('');
        $this->info('=== Análisis SUNAT ===');

        $isSunatIssuer = false;
        foreach (['CN', 'O'] as $k) {
            if (!empty($issuer[$k]) && stripos($issuer[$k], 'SUNAT') !== false) {
                $isSunatIssuer = true;
            }
        }
        if ($isSunatIssuer) {
            $this->info('✅ Emitido por una entidad relacionada a SUNAT');
        } else {
            $this->warn('⚠️ El issuer no parece ser SUNAT directamente');
            $this->warn('   (Puede ser una entidad intermediaria como IANUS)');
        }

        $this->line('');
        $this->line('Certificado registrado en:');
        $this->line('  SUNAT Operaciones en Línea > Factura Electrónica > Registrar Certificado');
        $this->line('  Verificar que esté en PRODUCCIÓN (no solo BETA)');

        return 0;
    }
}
