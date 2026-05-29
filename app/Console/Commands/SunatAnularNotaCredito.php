<?php

namespace App\Console\Commands;

use Greenter\Model\Client\Client;
use Greenter\Model\Company\Company;
use Greenter\Model\Company\Address;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\Legend;
use Illuminate\Console\Command;

class SunatAnularNotaCredito extends Command
{
    protected $signature = 'sunat:anular-nc
                            {--serie= : Serie de la boleta original (ej: B001)}
                            {--numero= : Número de la boleta original (ej: 4)}
                            {--monto= : Monto de la boleta original (ej: 500)}
                            {--nc-serie= : Serie para la Nota de Crédito (default: NC01)}
                            {--nc-numero= : Número correlativo de la NC (default: 1)}';
    protected $description = 'Anula una boleta emitiendo una Nota de Crédito Electrónica (tipo 07) vía sendBill';

    public function handle(): int
    {
        $serie = $this->option('serie') ?? $this->ask('Serie de la boleta original');
        $numero = $this->option('numero') ?? $this->ask('Número de la boleta original');
        $monto = $this->option('monto') ?? $this->ask('Monto de la boleta original');
        $ncSerie = $this->option('nc-serie') ?? 'NC01';
        $ncNumero = str_pad($this->option('nc-numero') ?? '1', 8, '0', STR_PAD_LEFT);

        $docId = $serie . '-' . $numero;

        $this->info("=== ANULACIÓN VÍA NOTA DE CRÉDITO ===");
        $this->line("Boleta original: {$docId}");
        $this->line("Monto: S/ {$monto}");
        $this->line("Nota Crédito: {$ncSerie}-{$ncNumero}");
        $this->line("");

        try {
            $factory = app(\App\Services\Sunat\GreenterFactory::class);
            $see = $factory->create(true);

            $client = new Client();
            $client->setTipoDoc('0')
                   ->setNumDoc('0')
                   ->setRznSocial('CLIENTES VARIOS');

            $address = new Address();
            $address->setUbigueo(config('sunat.ubigeo'))
                    ->setDepartamento(config('sunat.departamento'))
                    ->setProvincia(config('sunat.provincia'))
                    ->setDistrito(config('sunat.distrito'))
                    ->setDireccion(config('sunat.direccion'))
                    ->setCodLocal('0000');

            $company = new Company();
            $company->setRuc(config('sunat.ruc'))
                    ->setRazonSocial(config('sunat.razon_social'))
                    ->setNombreComercial(config('sunat.nombre_comercial'))
                    ->setAddress($address);

            $note = new Note();
            $note->setUblVersion('2.1')
                ->setTipoDoc('07')
                ->setSerie($ncSerie)
                ->setCorrelativo($ncNumero)
                ->setFechaEmision(now())
                ->setTipoMoneda('PEN')
                ->setClient($client)
                ->setCompany($company)
                ->setCodMotivo('01')
                ->setDesMotivo('Anulacion de boleta')
                ->setTipDocAfectado('03')
                ->setNumDocfectado($docId)
                ->setMtoOperExoneradas((float) $monto)
                ->setMtoIGV(0)
                ->setMtoOperGravadas(0)
                ->setMtoOperInafectas(0)
                ->setTotalImpuestos(0)
                ->setValorVenta((float) $monto)
                ->setSubTotal((float) $monto)
                ->setMtoImpVenta((float) $monto);

            $detail = new SaleDetail();
            $detail->setCodProducto('YAPE001')
                   ->setUnidad('ZZ')
                   ->setCantidad(1)
                   ->setDescripcion('Anulacion boleta ' . $docId)
                   ->setMtoBaseIgv((float) $monto)
                   ->setPorcentajeIgv(18)
                   ->setIgv(0)
                   ->setTipAfeIgv('20')
                   ->setTotalImpuestos(0)
                   ->setMtoValorVenta((float) $monto)
                   ->setMtoValorUnitario((float) $monto)
                   ->setMtoPrecioUnitario((float) $monto);

            $note->setDetails([$detail]);

            $this->info("Enviando Nota de Crédito a SUNAT...");
            $result = $see->send($note);
            $xml = $see->getFactory()->getLastXml();

            $xmlPath = 'sunat/xml/NC-' . $ncSerie . '-' . $ncNumero . '.xml';
            $xmlFull = storage_path('app/' . $xmlPath);
            if (!is_dir(dirname($xmlFull))) {
                mkdir(dirname($xmlFull), 0755, true);
            }
            file_put_contents($xmlFull, $xml);
            $this->line("XML guardado en: {$xmlPath}");

            if (!$result->isSuccess()) {
                $error = $result->getError();
                $this->error("SUNAT rechazó la Nota de Crédito:");
                $this->error("  Código: {$error->getCode()}");
                $this->error("  Mensaje: {$error->getMessage()}");

                // Guardar traza SOAP
                try {
                    $ref = new \ReflectionProperty(\Greenter\See::class, 'wsClient');
                    $ref->setAccessible(true);
                    $client = $ref->getValue($see);
                    if ($client instanceof \SoapClient) {
                        $traceFile = storage_path('app/sunat/xml/NC-ERROR-' . $ncSerie . '-' . $ncNumero . '-soap.txt');
                        file_put_contents($traceFile, "REQUEST:\n" . $client->__getLastRequest() . "\n\nRESPONSE:\n" . $client->__getLastResponse());
                        $this->line("  Traza SOAP guardada en: NC-ERROR-" . $ncSerie . '-' . $ncNumero . '-soap.txt');
                    }
                } catch (\Throwable $t) {}
                return 1;
            }

            $cdr = $result->getCdrResponse();
            $estado = $cdr && (int)$cdr->getCode() === 0 ? 'ACEPTADA' : 'RECHAZADA';

            $cdrPath = null;
            try {
                $cdrZip = $result->getCdrZip();
                if ($cdrZip) {
                    $cdrPath = 'sunat/cdr/R-NC-' . $ncSerie . '-' . $ncNumero . '.zip';
                    $cdrFull = storage_path('app/' . $cdrPath);
                    if (!is_dir(dirname($cdrFull))) {
                        mkdir(dirname($cdrFull), 0755, true);
                    }
                    file_put_contents($cdrFull, $cdrZip);
                }
            } catch (\Throwable $e) {}

            $this->info("RESULTADO: {$estado}");
            if ($cdr) {
                $this->line("  Código CDR: " . $cdr->getCode());
                $this->line("  Descripción: " . $cdr->getDescription());
                $notas = $cdr->getNotes();
                if (!empty($notas)) {
                    $this->line("  Notas: " . implode(' | ', $notas));
                }
            }
            if ($cdrPath) {
                $this->line("  CDR guardado en: {$cdrPath}");
            }

            if ($estado === 'ACEPTADA') {
                $this->info("✅ BOLETA {$docId} ANULADA EXITOSAMENTE");
                return 0;
            }

            $this->error("❌ Nota de Crédito RECHAZADA");
            return 1;

        } catch (\Exception $e) {
            $this->error("Excepción: " . $e->getMessage());
            return 1;
        }
    }
}
