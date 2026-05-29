<?php

namespace App\Services\Sunat;

use Greenter\Model\Voided\Voided;
use Greenter\Model\Voided\VoidedDetail;
use Greenter\Model\Company\Company;

class VoidedBuilder
{
    public function build(array $boletas, string $correlativo, string $fechaGeneracion, Company $company): Voided
    {
        $details = [];
        foreach ($boletas as $b) {
            $detalle = new VoidedDetail();
            $detalle->setTipoDoc('03')
                ->setSerie($b['serie'])
                ->setCorrelativo(str_pad((string) $b['numero'], 8, '0', STR_PAD_LEFT))
                ->setMonto($b['monto'] ?? 0)
                ->setDesMotivoBaja($b['motivo'] ?? 'Anulacion');
            $details[] = $detalle;
        }

        $voided = new Voided();
        $voided->setCorrelativo($correlativo)
                ->setFecGeneracion(new \DateTimeImmutable($fechaGeneracion))
                ->setFecComunicacion(new \DateTimeImmutable())
                ->setCompany($company)
                ->setDetails($details);

        return $voided;
    }
}
