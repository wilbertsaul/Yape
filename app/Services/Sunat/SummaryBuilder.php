<?php

namespace App\Services\Sunat;

use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;
use Greenter\Model\Company\Company;

class SummaryBuilder
{
    public function build(array $boletas, string $correlativo, string $fecha, Company $company, string $estado = '1'): Summary
    {
        $details = [];
        foreach ($boletas as $b) {
            $clienteTipo = $b['cliente_tipo'] ?? '0';
            $clienteNro = $b['cliente_nro'] ?? '0';

            $detalle = new SummaryDetail();
            $detalle->setTipoDoc('03')
                ->setSerieNro($b['serie'] . '-' . str_pad($b['numero'], 8, '0', STR_PAD_LEFT))
                ->setEstado($estado)
                ->setClienteTipo($clienteTipo)
                ->setClienteNro($clienteNro)
                ->setTotal((float) $b['monto'])
                ->setMtoOperExoneradas((float) $b['monto'])
                ->setMtoIGV(0)
                ->setMtoOperGravadas(0)
                ->setMtoOperInafectas(0);
            $details[] = $detalle;
        }

        $summary = new Summary();
        $summary->setFecGeneracion(new \DateTimeImmutable($fecha))
                ->setFecResumen(new \DateTimeImmutable($fecha))
                ->setCorrelativo($correlativo)
                ->setCompany($company)
                ->setDetails($details);

        return $summary;
    }
}
