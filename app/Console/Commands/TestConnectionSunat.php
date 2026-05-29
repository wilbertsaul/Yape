<?php

namespace App\Console\Commands;

use Greenter\Ws\Services\SoapClient;
use Greenter\Ws\Services\SunatEndpoints;
use Greenter\Ws\Services\WsdlProvider;
use Illuminate\Console\Command;

class TestConnectionSunat extends Command
{
    protected $signature = 'sunat:test-connection';
    protected $description = 'Prueba conexión SOAP con SUNAT en producción y muestra traza completa';

    public function handle(): int
    {
        $ruc = config('sunat.ruc');
        $user = config('sunat.usuario_sol');
        $password = config('sunat.clave_sol');
        $ambiente = config('sunat.ambiente', 'beta');

        $this->info("RUC: $ruc");
        $this->info("Usuario SOL: $ruc$user");
        $this->info("Ambiente: $ambiente");
        $this->info('');

        $endpoint = $ambiente === 'beta'
            ? SunatEndpoints::FE_BETA
            : SunatEndpoints::FE_PRODUCCION;

        $this->info("Endpoint: $endpoint");
        $this->info("WSDL: " . WsdlProvider::getBillPath());
        $this->info('');

        try {
            $start = microtime(true);

            $client = new SoapClient(WsdlProvider::getBillPath(), [
                'trace' => 1,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ]),
            ]);

            $client->setCredentials($ruc . $user, $password);
            $client->setService($endpoint);

            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $this->info("✅ Cliente SOAP creado en {$elapsed}ms");
            $this->line('');

            // Probar conexión enviando un resumen vacío (generará error controlado de SUNAT)
            $this->output->write('Enviando llamada de prueba... ');
            $start = microtime(true);

            $params = [
                'fileName' => 'test-connection.zip',
                'contentFile' => base64_encode('test'),
            ];

            $client->call('sendSummary', ['parameters' => $params]);

            $this->warn(' (no debería llegar aquí - SUNAT aceptó datos inválidos)');

        } catch (\SoapFault $e) {
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $this->line("respondió en {$elapsed}ms");
            $this->line('');

            $this->error("SoapFault: {$e->getMessage()}");
            $this->line("FaultCode: {$e->faultcode}");

            $detail = null;
            if (isset($e->detail)) {
                $detail = json_encode($e->detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $this->line('Fault Detail:');
                $this->line($detail);
            }
            $this->line('');

            // SOAP trace
            $this->info('--- SOAP REQUEST HEADERS ---');
            $this->line($client->__getLastRequestHeaders() ?? '(vacio)');
            $this->line('');
            $this->info('--- SOAP REQUEST BODY ---');
            $this->line($client->__getLastRequest() ?? '(vacio)');
            $this->line('');
            $this->info('--- SOAP RESPONSE HEADERS ---');
            $this->line($client->__getLastResponseHeaders() ?? '(vacio)');
            $this->line('');
            $this->info('--- SOAP RESPONSE BODY ---');
            $this->line($client->__getLastResponse() ?? '(vacio)');
            $this->line('');

            return 1;
        } catch (\Exception $e) {
            $this->error("Excepción: " . get_class($e) . ': ' . $e->getMessage());
            $this->line('');

            try {
                $this->info('--- SOAP REQUEST ---');
                $this->line($client->__getLastRequest() ?? '(vacio)');
                $this->line('');
                $this->info('--- SOAP RESPONSE ---');
                $this->line($client->__getLastResponse() ?? '(vacio)');
            } catch (\Throwable $t) {
                $this->warn('No se pudo capturar traza SOAP: ' . $t->getMessage());
            }

            return 1;
        }

        return 0;
    }
}
