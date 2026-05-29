<?php

namespace App\Services\Sunat;

use Greenter\See;
use Greenter\Ws\Services\ConsultCdrService;
use Greenter\Ws\Services\SoapClient;
use Greenter\Ws\Services\SunatEndpoints;
use Greenter\Ws\Services\WsdlProvider;

class GreenterFactory
{
    public function create(bool $trace = false): See
    {
        $oldTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '20');

        try {
            $see = new See();
            $see->setCertificate(app(CertificadoService::class)->getPem());

            if ($trace) {
                $this->injectTraceableClient($see);
            } else {
                $endpoint = $this->getEndpoint();
                $see->setService($endpoint);
            }

            $see->setClaveSOL(
                config('sunat.ruc'),
                config('sunat.usuario_sol_sec'),
                config('sunat.clave_sol_sec')
            );

            $cachePath = storage_path('app/sunat/cache');
            if (!is_dir($cachePath)) mkdir($cachePath, 0755, true);
            $see->setCachePath($cachePath);

            return $see;
        } finally {
            ini_set('default_socket_timeout', $oldTimeout);
        }
    }

    private function injectTraceableClient(See $see): void
    {
        $params = [
            'trace' => 1,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]),
        ];

        $client = new SoapClient(WsdlProvider::getBillPath(), $params);
        $client->setCredentials(
            config('sunat.ruc') . config('sunat.usuario_sol_sec'),
            config('sunat.clave_sol_sec')
        );
        $client->setService($this->getEndpoint());

        $ref = new \ReflectionProperty(See::class, 'wsClient');
        $ref->setAccessible(true);
        $ref->setValue($see, $client);
    }

    public function createConsultService(): ConsultCdrService
    {
        $oldTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '20');

        try {
            $client = new SoapClient(WsdlProvider::getConsultPath());
            $client->setCredentials(
                config('sunat.ruc') . config('sunat.usuario_sol_sec'),
                config('sunat.clave_sol_sec')
            );

            $endpoint = $this->getConsultEndpoint();
            $client->setService($endpoint);

            $service = new ConsultCdrService();
            $service->setClient($client);

            return $service;
        } finally {
            ini_set('default_socket_timeout', $oldTimeout);
        }
    }

    private function getEndpoint(): string
    {
        return config('sunat.ambiente') === 'beta'
            ? SunatEndpoints::FE_BETA
            : SunatEndpoints::FE_PRODUCCION;
    }

    private function getConsultEndpoint(): string
    {
        if (config('sunat.ambiente') === 'beta') {
            return 'https://e-beta.sunat.gob.pe/ol-it-wsconscpegem/billConsultService';
        }
        return SunatEndpoints::FE_CONSULTA_CDR;
    }
}
