<?php

namespace App\Services\Sunat;

use Greenter\See;
use Greenter\Ws\Services\ConsultCdrService;
use Illuminate\Support\Facades\Log;

class SunatSender
{
    public function __construct(
        private See $see,
        private ?ConsultCdrService $consultCdr = null,
    ) {}

    public function enviarResumen(array $boletas, string $ticketCorrelativo, string $fecha): array
    {
        $company = $this->buildCompany();
        $builder = app(SummaryBuilder::class);
        $summary = $builder->build($boletas, $ticketCorrelativo, $fecha, $company);

        try {
            $result = $this->see->send($summary);
            $xml = $this->see->getFactory()->getLastXml();

            if (!$result->isSuccess()) {
                $soapTrace = $this->getSoapTrace();
                Log::error('SUNAT send no success', [
                    'error' => $result->getError()->getMessage(),
                    'code' => $result->getError()->getCode(),
                    'soap_trace' => $soapTrace,
                ]);

                return [
                    'success' => false,
                    'error' => $result->getError()->getMessage(),
                    'code' => $result->getError()->getCode(),
                    'xml' => $xml,
                    'soap_trace' => $soapTrace,
                ];
            }

            $ticket = $result->getTicket();

            return [
                'success' => true,
                'ticket' => $ticket,
                'xml' => $xml,
                'pending' => true,
            ];
        } catch (\Exception $e) {
            $soapTrace = $this->getSoapTrace();
            Log::error('SUNAT send exception', [
                'message' => $e->getMessage(),
                'boletas' => $boletas,
                'soap_trace' => $soapTrace,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'soap_trace' => $soapTrace,
            ];
        }
    }

    private function getSoapTrace(): ?array
    {
        try {
            $ref = new \ReflectionProperty(See::class, 'wsClient');
            $ref->setAccessible(true);
            $client = $ref->getValue($this->see);

            if (!$client instanceof \SoapClient) {
                return null;
            }

            $trace = [];
            try { $trace['request'] = $client->__getLastRequest(); } catch (\Throwable $e) {}
            try { $trace['response'] = $client->__getLastResponse(); } catch (\Throwable $e) {}
            try { $trace['request_headers'] = $client->__getLastRequestHeaders(); } catch (\Throwable $e) {}
            try { $trace['response_headers'] = $client->__getLastResponseHeaders(); } catch (\Throwable $e) {}

            return empty($trace['request'] ?? null) && empty($trace['response'] ?? null) ? null : $trace;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function enviarAnulacion(array $boletas, string $fecha, string $correlativo): array
    {
        $company = $this->buildCompany();
        $builder = app(VoidedBuilder::class);
        $voided = $builder->build($boletas, $correlativo, $fecha, $company);

        try {
            $result = $this->see->send($voided);
            $xml = $this->see->getFactory()->getLastXml();

            if (!$result->isSuccess()) {
                $error = $result->getError();
                $soapTrace = $this->getSoapTrace();
                return [
                    'success' => false,
                    'code' => $error->getCode(),
                    'description' => $error->getMessage(),
                    'xml' => $xml,
                    'soap_trace' => $soapTrace,
                ];
            }

            return [
                'success' => true,
                'ticket' => $result->getTicket(),
                'code' => 0,
                'xml' => $xml,
            ];
        } catch (\Exception $e) {
            Log::error('SUNAT enviarAnulacion exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function consultarTicket(string $ticket): array
    {
        try {
            $result = $this->see->getStatus($ticket);

            Log::info('SUNAT status response', [
                'ticket' => $ticket,
                'code' => $result->getCode(),
                'isSuccess' => $result->isSuccess(),
                'error' => $result->getError()?->getMessage(),
            ]);

            if ($result->getCode() === '98') {
                return [
                    'success' => true,
                    'pending' => true,
                    'code' => '98',
                ];
            }

            if (!$result->isSuccess()) {
                $msg = $result->getError()?->getMessage() ?? 'Error desconocido de SUNAT';
                return [
                    'success' => false,
                    'error' => $msg,
                ];
            }

            $cdr = $result->getCdrResponse();
            return [
                'success' => true,
                'pending' => false,
                'cdr_code' => (int) $cdr->getCode(),
                'cdr_description' => $cdr->getDescription(),
                'cdr_notes' => $cdr->getNotes(),
                'cdr_zip' => $result->getCdrZip(),
            ];
        } catch (\Exception $e) {
            Log::error('SUNAT consultarTicket exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function consultarPorDocumento(string $ruc, string $tipoDoc, string $serie, int $numero): array
    {
        if (!$this->consultCdr) {
            return ['success' => false, 'error' => 'ConsultCdrService no disponible'];
        }

        try {
            $result = $this->consultCdr->getStatusCdr($ruc, $tipoDoc, $serie, $numero);

            Log::info('SUNAT consultarPorDocumento response', [
                'ruc' => $ruc,
                'tipo' => $tipoDoc,
                'serie' => $serie,
                'numero' => $numero,
                'code' => $result->getCode(),
                'message' => $result->getMessage(),
                'isSuccess' => $result->isSuccess(),
                'error' => $result->getError()?->getMessage(),
            ]);

            if (!$result->isSuccess()) {
                $msg = $result->getError()?->getMessage() ?: $result->getMessage() ?: 'Error desconocido';

                // "Not Found" significa que SUNAT aún no procesa el resumen → es pendiente, no error
                if (stripos($msg, 'not found') !== false || stripos($msg, '2041') !== false) {
                    return [
                        'success' => true,
                        'pending' => true,
                        'code' => '98',
                    ];
                }

                return [
                    'success' => false,
                    'error' => $msg,
                ];
            }

            $cdr = $result->getCdrResponse();

            if ($cdr) {
                return [
                    'success' => true,
                    'pending' => false,
                    'cdr_code' => (int) $cdr->getCode(),
                    'cdr_description' => $cdr->getDescription(),
                    'cdr_notes' => $cdr->getNotes(),
                    'cdr_zip' => $result->getCdrZip(),
                ];
            }

            return [
                'success' => true,
                'pending' => true,
                'code' => $result->getCode(),
                'message' => $result->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('SUNAT consultarPorDocumento exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function pollTicket(string $ticket, int $maxRetries = 10): array
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            sleep(1);

            try {
                $result = $this->see->getStatus($ticket);

                if ($result->getCode() === '98') {
                    continue;
                }

                if (!$result->isSuccess()) {
                    return [
                        'success' => false,
                        'error' => $result->getError()->getMessage(),
                    ];
                }

                $cdr = $result->getCdrResponse();
                return [
                    'success' => true,
                    'cdr_code' => (int) $cdr->getCode(),
                    'cdr_description' => $cdr->getDescription(),
                    'cdr_notes' => $cdr->getNotes(),
                    'cdr_zip' => $result->getCdrZip(),
                ];
            } catch (\Exception $e) {
                Log::warning('SUNAT poll retry: ' . $e->getMessage());
                continue;
            }
        }

        return [
            'success' => false,
            'error' => 'SUNAT no respondió después de ' . $maxRetries . ' intentos',
        ];
    }

    private function buildCompany(): \Greenter\Model\Company\Company
    {
        $address = new \Greenter\Model\Company\Address();
        $address->setUbigueo(config('sunat.ubigeo'))
                ->setDepartamento(config('sunat.departamento'))
                ->setProvincia(config('sunat.provincia'))
                ->setDistrito(config('sunat.distrito'))
                ->setDireccion(config('sunat.direccion'))
                ->setCodLocal('0000');

        $company = new \Greenter\Model\Company\Company();
        $company->setRuc(config('sunat.ruc'))
                ->setRazonSocial(config('sunat.razon_social'))
                ->setNombreComercial(config('sunat.nombre_comercial'))
                ->setAddress($address);

        return $company;
    }
}
