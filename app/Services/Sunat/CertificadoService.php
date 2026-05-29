<?php

namespace App\Services\Sunat;

use Greenter\XMLSecLibs\Certificate\X509Certificate;
use Greenter\XMLSecLibs\Certificate\X509ContentType;

class CertificadoService
{
    public function getPem(): string
    {
        $path = config('sunat.certificate_path');
        $password = config('sunat.certificate_password');

        if (!file_exists($path)) {
            throw new \RuntimeException('Certificate not found at: ' . $path);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'pem') {
            $pem = file_get_contents($path);
            if ($pem === false) {
                throw new \RuntimeException('Cannot read PEM file: ' . $path);
            }
            return $pem;
        }

        $pemPath = dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME) . '.pem';
        if (file_exists($pemPath)) {
            $pem = file_get_contents($pemPath);
            if ($pem !== false) {
                return $pem;
            }
        }

        $pfx = file_get_contents($path);
        $cert = new X509Certificate($pfx, $password);

        return $cert->export(X509ContentType::PEM);
    }
}
