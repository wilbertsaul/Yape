<?php

namespace App\Console\Commands;

use App\Models\Transaccion;
use App\Services\Sunat\BoletaDataBuilder;
use App\Services\Sunat\GreenterFactory;
use Illuminate\Console\Command;

class SendBoletaSunat extends Command
{
    protected $signature = 'sunat:send-boleta
        {id : ID de la transacción}
        {--use-summary : Usar sendSummary en vez de sendBill}';

    protected $description = 'Envía una boleta individual a SUNAT vía sendBill (no resumen)';

    public function handle(GreenterFactory $factory): int
    {
        $tx = Transaccion::find($this->argument('id'));
        if (!$tx) {
            $this->error("Transacción #{$this->argument('id')} no encontrada");
            return 1;
        }

        $this->info("Transacción #{$tx->id}: {$tx->boleta_serie}-{$tx->boleta_numero}");
        $this->info("Monto: S/ {$tx->monto_transferencia}");
        $this->info("Cliente: " . ($tx->cliente->dni ?? 'genérico'));
        $this->info('');

        $metodo = $this->option('use-summary') ? 'sendSummary' : 'sendBill';
        $this->info("Método: {$metodo}");
        $this->info('');

        try {
            $see = $factory->create(true);

            $builder = app(BoletaDataBuilder::class);
            $correlativoNumerico = (int) $tx->boleta_numero;

            if ($this->option('use-summary')) {
                $this->warn('Usando resumen diario...');
                $company = $this->buildCompany();
                $boletas = [[
                    'serie' => $tx->boleta_serie,
                    'numero' => $tx->boleta_numero,
                    'monto' => (float) $tx->monto_transferencia,
                    'cliente_tipo' => '0',
                    'cliente_nro' => '0',
                ]];
                $summaryBuilder = app(\App\Services\Sunat\SummaryBuilder::class);
                $summary = $summaryBuilder->build($boletas, 'RC-TEST-' . $tx->id, $tx->fecha_hora->format('Y-m-d'), $company);
                $result = $see->send($summary);
                $xml = $see->getFactory()->getLastXml();
            } else {
                $invoice = $builder->build($tx, $correlativoNumerico, $tx->boleta_serie, $correlativoNumerico);
                $result = $see->send($invoice);
                $xml = $see->getFactory()->getLastXml();
            }

            $soapTrace = $this->getSoapTrace($see);

            $this->line('');
            $this->info('=== RESULTADO ===');

            if ($result->isSuccess()) {
                $this->info('✅ Envío exitoso');

                if ($this->option('use-summary')) {
                    $this->info('Ticket: ' . $result->getTicket());
                } else {
                    $cdr = $result->getCdrResponse();
                    if ($cdr) {
                        $this->info('Código CDR: ' . $cdr->getCode());
                        $this->info('Descripción: ' . $cdr->getDescription());
                        $notes = $cdr->getNotes();
                        if ($notes) {
                            $this->info('Notas: ' . implode(', ', $notes));
                        }
                    }
                }
            } else {
                $this->error('❌ Error: ' . $result->getError()->getMessage());
                $this->warn('Código: ' . $result->getError()->getCode());
            }

            $this->line('');
            if ($xml) {
                $this->info('--- XML ---');
                $this->line($xml);
            }

            if ($soapTrace) {
                $this->line('');
                $this->info('--- SOAP REQUEST ---');
                $this->line($soapTrace['request'] ?? '(vacio)');
                $this->line('');
                $this->info('--- SOAP RESPONSE ---');
                $this->line($soapTrace['response'] ?? '(vacio)');
                $this->line('');
                $this->info('--- SOAP REQUEST HEADERS ---');
                $this->line($soapTrace['request_headers'] ?? '(vacio)');
                $this->line('');
                $this->info('--- SOAP RESPONSE HEADERS ---');
                $this->line($soapTrace['response_headers'] ?? '(vacio)');
            }

            return $result->isSuccess() ? 0 : 1;
        } catch (\Throwable $e) {
            $this->error('Excepción: ' . get_class($e) . ': ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }

    private function getSoapTrace($see): ?array
    {
        try {
            $ref = new \ReflectionProperty(\Greenter\See::class, 'wsClient');
            $ref->setAccessible(true);
            $client = $ref->getValue($see);

            if (!$client instanceof \SoapClient) return null;

            $trace = [];
            try { $trace['request'] = $client->__getLastRequest(); } catch (\Throwable $e) {}
            try { $trace['response'] = $client->__getLastResponse(); } catch (\Throwable $e) {}
            try { $trace['request_headers'] = $client->__getLastRequestHeaders(); } catch (\Throwable $e) {}
            try { $trace['response_headers'] = $client->__getLastResponseHeaders(); } catch (\Throwable $e) {}

            return empty($trace['request'] ?? null) && empty($trace['response'] ?? null) ? null : $trace;
        } catch (\Throwable $e) {
            return null;
        }
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
