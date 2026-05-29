<?php

namespace App\Services\Sunat;

use App\Models\Transaccion;
use App\Services\NumeroALetras;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Company;
use Greenter\Model\Company\Address;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;

class BoletaDataBuilder
{
    public function build(Transaccion $transaccion, int $correlativo, ?string $serieOverride = null, ?int $correlativoOverride = null): Invoice
    {
        $monto = (float) ($transaccion->comision ?? $transaccion->monto_transferencia);
        $serie = $serieOverride ?? config('sunat.serie', 'EB01');
        $correlativoStr = $correlativoOverride ? (string) $correlativoOverride : (string) $correlativo;

        $client = new Client();
        if ($transaccion->cliente && $transaccion->cliente->dni) {
            $client->setTipoDoc('1')
                   ->setNumDoc($transaccion->cliente->dni)
                   ->setRznSocial($transaccion->cliente->nombre_completo);
        } else {
            $client->setTipoDoc('0')
                   ->setNumDoc('0')
                   ->setRznSocial('CLIENTES VARIOS');
        }

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

        $invoice = new Invoice();
        $invoice->setUblVersion('2.1')
                ->setTipoOperacion('0101')
                ->setTipoDoc('03')
                ->setSerie($serie)
                ->setCorrelativo($correlativoStr)
                ->setFechaEmision(now())
                ->setFormaPago(new FormaPagoContado())
                ->setTipoMoneda('PEN')
                ->setClient($client)
                ->setCompany($company);

        $invoice->setMtoOperGravadas(0)
                ->setMtoOperExoneradas($monto)
                ->setMtoOperInafectas(0)
                ->setMtoIGV(0)
                ->setTotalImpuestos(0)
                ->setValorVenta($monto)
                ->setSubTotal($monto)
                ->setMtoImpVenta($monto);

        $detail = new SaleDetail();
        $detail->setCodProducto('YAPE001')
               ->setUnidad('ZZ')
               ->setCantidad(1)
               ->setDescripcion('Comision por servicio Yape')
               ->setMtoBaseIgv($monto)
               ->setPorcentajeIgv(18)
               ->setIgv(0)
               ->setTipAfeIgv('20')
               ->setTotalImpuestos(0)
               ->setMtoValorVenta($monto)
               ->setMtoValorUnitario($monto)
               ->setMtoPrecioUnitario($monto);

        $legend = new Legend();
        $legend->setCode('1000')
               ->setValue(NumeroALetras::convertir($monto));

        $invoice->setDetails([$detail])->setLegends([$legend]);

        return $invoice;
    }
}
