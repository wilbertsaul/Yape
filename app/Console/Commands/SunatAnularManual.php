<?php

namespace App\Console\Commands;

use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;
use Illuminate\Console\Command;

class SunatAnularManual extends Command
{
    protected $signature = 'sunat:anular-manual
                            {--serie= : Serie de la boleta (ej: B001)}
                            {--numero= : Número de la boleta (ej: 4)}
                            {--monto= : Monto de la boleta (ej: 500)}
                            {--fecha= : Fecha de emisión (Y-m-d, ej: 2026-05-21)}
                            {--cliente-tipo=0 : Tipo de documento del cliente}
                            {--cliente-nro=0 : Número de documento del cliente}';
    protected $description = 'Envía una anulación (RC) manual a SUNAT sin depender de un registro en BD';

    public function handle(): int
    {
        $serie = $this->option('serie') ?? $this->ask('Serie de la boleta');
        $numero = $this->option('numero') ?? $this->ask('Número de la boleta');
        $monto = $this->option('monto') ?? $this->ask('Monto de la boleta');
        $fecha = $this->option('fecha') ?? $this->ask('Fecha de emisión (Y-m-d)');
        $clienteTipo = $this->option('cliente-tipo');
        $clienteNro = $this->option('cliente-nro');

        $this->info("=== ANULACIÓN MANUAL VÍA RC ===");
        $this->line("Serie:    $serie");
        $this->line("Número:   $numero");
        $this->line("Monto:    S/ {$monto}");
        $this->line("Fecha:    {$fecha}");
        $this->line("Cliente:  tipo={$clienteTipo} nro={$clienteNro}");
        $this->line("");

        $correlativoKey = 'RC-' . now()->format('Ymd');
        $correlativoFile = storage_path('app/sunat/summary_counter.json');
        $counters = [];
        if (file_exists($correlativoFile)) {
            $counters = json_decode(file_get_contents($correlativoFile), true) ?? [];
        }
        $counters[$correlativoKey] = ($counters[$correlativoKey] ?? 0) + 1;
        file_put_contents($correlativoFile, json_encode($counters));
        $correlativo = str_pad((string) $counters[$correlativoKey], 3, '0', STR_PAD_LEFT);

        $this->line("Correlativo RC: {$correlativo}");

        try {
            $factory = app(\App\Services\Sunat\GreenterFactory::class);
            $see = $factory->create(true);

            $company = $this->buildCompany();

            $detalle = new SummaryDetail();
            $detalle->setTipoDoc('03')
                ->setSerieNro($serie . '-' . str_pad((string) $numero, 8, '0', STR_PAD_LEFT))
                ->setEstado('2')
                ->setClienteTipo($clienteTipo)
                ->setClienteNro($clienteNro)
                ->setTotal((float) $monto)
                ->setMtoOperExoneradas((float) $monto)
                ->setMtoIGV(0)
                ->setMtoOperGravadas(0)
                ->setMtoOperInafectas(0);

            $summary = new Summary();
            $summary->setCorrelativo($correlativo)
                ->setFecGeneracion(new \DateTimeImmutable($fecha))
                ->setFecResumen(new \DateTimeImmutable())
                ->setCompany($company)
                ->setDetails([$detalle]);

            $this->info("Enviando anulación a SUNAT...");
            $result = $see->send($summary);

            if (!$result->isSuccess()) {
                $error = $result->getError();
                $this->error("SUNAT rechazó el envío:");
                $this->error("  Código: {$error->getCode()}");
                $this->error("  Mensaje: {$error->getMessage()}");
                return 1;
            }

            $ticket = $result->getTicket();
            $this->info("Anulación enviada exitosamente.");
            $this->line("  Ticket: {$ticket}");

            $xmlPath = 'sunat/xml/RC-MANUAL-' . $correlativo . '.xml';
            $xmlFull = storage_path('app/' . $xmlPath);
            if (!is_dir(dirname($xmlFull))) {
                mkdir(dirname($xmlFull), 0755, true);
            }
            file_put_contents($xmlFull, $see->getFactory()->getLastXml());
            $this->line("  XML guardado en: {$xmlPath}");

            $this->line("");
            $this->info("Consultando ticket en 3 segundos...");
            sleep(3);

            $sender = new \App\Services\Sunat\SunatSender($see);
            $maxAttempts = 10;
            $attempt = 0;

            while ($attempt < $maxAttempts) {
                $attempt++;
                $this->line("Intento {$attempt}/{$maxAttempts}...");
                $checkResult = $sender->consultarTicket($ticket);

                if (!empty($checkResult['pending'])) {
                    $this->warn("  Pendiente, reintentando en 5s...");
                    sleep(5);
                    continue;
                }

                if (!empty($checkResult['success'])) {
                    $cdrCode = (int) ($checkResult['cdr_code'] ?? -1);
                    $cdrDesc = $checkResult['cdr_description'] ?? '';
                    $cdrNotes = $checkResult['cdr_notes'] ?? [];

                    $this->info("Respuesta de SUNAT:");
                    $this->line("  Código CDR: {$cdrCode}");
                    $this->line("  Descripción: {$cdrDesc}");
                    if (!empty($cdrNotes)) {
                        $this->line("  Notas: " . implode(' | ', $cdrNotes));
                    }

                    if ($cdrCode === 0) {
                        $this->info("✅ ANULACIÓN ACEPTADA POR SUNAT");

                        $cdrPath = 'sunat/cdr/R-MANUAL-' . $correlativo . '.zip';
                        if (!empty($checkResult['cdr_zip'])) {
                            $cdrFull = storage_path('app/' . $cdrPath);
                            if (!is_dir(dirname($cdrFull))) {
                                mkdir(dirname($cdrFull), 0755, true);
                            }
                            file_put_contents($cdrFull, $checkResult['cdr_zip']);
                            $this->line("  CDR guardado en: {$cdrPath}");
                        }

                        return 0;
                    }

                    if ($cdrCode === 98) {
                        $this->warn("Código 98 (pendiente), reintentando en 5s...");
                        sleep(5);
                        continue;
                    }

                    $this->error("❌ ANULACIÓN RECHAZADA (código {$cdrCode})");
                    return 1;
                }

                $this->error("Error al consultar ticket: " . ($checkResult['error'] ?? 'desconocido'));
                return 1;
            }

            $this->warn("Se agotaron los intentos de consulta. El ticket {$ticket} quedó pendiente.");
            $this->line("Debe consultar manualmente más tarde.");
            return 2;

        } catch (\Exception $e) {
            $this->error("Excepción: " . $e->getMessage());
            return 1;
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
