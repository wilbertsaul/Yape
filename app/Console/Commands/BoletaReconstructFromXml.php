<?php

namespace App\Console\Commands;

use App\Models\CorrelativoBoleta;
use App\Models\Transaccion;
use Illuminate\Console\Command;

class BoletaReconstructFromXml extends Command
{
    protected $signature = 'boleta:reconstruct-from-xml
        {--dry-run : Mostrar qué se crearía sin insertar}
        {--force : Ejecutar sin confirmación}';

    protected $description = 'Reconstruye transacciones desde XML/CDR existentes';

    public function handle(): int
    {
        $this->info('=== RECONSTRUCCIÓN DE TRANSACCIONES DESDE XML ===');
        $this->info('');

        $xmlDir = storage_path('app/sunat/xml');
        $cdrDir = storage_path('app/sunat/cdr');

        if (!is_dir($xmlDir)) {
            $this->error("No se encuentra el directorio XML: $xmlDir");
            return 1;
        }

        // Paso 1: Recolectar todos los XMLs
        $this->info('Paso 1: Recolectando XMLs...');
        $xmlFiles = glob("$xmlDir/BILL-B*.xml");
        sort($xmlFiles);
        $this->info("  Encontrados " . count($xmlFiles) . " archivos.");

        $boletas = [];
        foreach ($xmlFiles as $f) {
            $dom = new \DOMDocument();
            $dom->load($f);
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
            $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

            $idStr = $xpath->query('//cbc:ID')->item(0)?->nodeValue ?? '';
            $parts = explode('-', $idStr);
            $serie = $parts[0] ?? '';
            $numero = (int) ($parts[1] ?? 0);

            $amount = (float) ($xpath->query('//cac:LegalMonetaryTotal/cbc:PayableAmount')->item(0)?->nodeValue ?? 0);
            $date = $xpath->query('//cbc:IssueDate')->item(0)?->nodeValue ?? '';
            $time = $xpath->query('//cbc:IssueTime')->item(0)?->nodeValue ?? '00:00:00';
            $client = $xpath->query('//cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName')->item(0)?->nodeValue ?? 'CLIENTES VARIOS';

            $xmlRelative = 'sunat/xml/' . basename($f);
            $cdrFile = "$cdrDir/R-BILL-$serie-$numero.zip";
            $cdrRelative = file_exists($cdrFile) ? 'sunat/cdr/' . basename($cdrFile) : null;

            $cdrCode = null;
            if ($cdrRelative) {
                $cdrCode = $this->extractCdrCode($cdrFile);
            }

            $boletas[] = [
                'serie' => $serie,
                'numero' => $numero,
                'amount' => $amount,
                'date' => $date,
                'time' => $time,
                'client' => $client,
                'xml_path' => $xmlRelative,
                'cdr_path' => $cdrRelative,
                'cdr_code' => $cdrCode,
                'estado' => $cdrCode === '0' ? 'ACEPTADA' : ($cdrCode ? 'RECHAZADA' : 'SIN_CDR'),
            ];
        }

        $maxB = max(array_column($boletas, 'numero'));
        $this->info("  Mayor correlativo encontrado: B001-$maxB");
        $this->info('');

        // Paso 2: Verificar qué boletas ya existen
        $this->info('Paso 2: Verificando registros existentes...');
        $existing = Transaccion::whereNotNull('boleta_xml_path')->pluck('boleta_serie', 'boleta_numero')->mapWithKeys(fn($s, $n) => ["$s-$n" => true]);

        $toCreate = [];
        $alreadyExist = 0;

        foreach ($boletas as $b) {
            $key = "{$b['serie']}-{$b['numero']}";
            if (isset($existing[$key])) {
                $alreadyExist++;
            } else {
                $toCreate[] = $b;
            }
        }

        $this->info("  Ya existen: $alreadyExist");
        $this->info("  Por crear: " . count($toCreate));
        $this->info('');

        if (empty($toCreate)) {
            $this->info('No hay boletas pendientes de reconstruir.');
            return 0;
        }

        // Mostrar resumen
        $this->info('Boletas a crear:');
        $this->table(
            ['Serie', 'Número', 'Monto', 'Fecha', 'Cliente', 'CDR'],
            array_map(fn($b) => [
                $b['serie'],
                $b['numero'],
                'S/' . number_format($b['amount'], 2),
                $b['date'],
                \Str::limit($b['client'], 25),
                $b['cdr_code'] ?? '-',
            ], array_slice($toCreate, 0, 30))
        );
        if (count($toCreate) > 30) {
            $this->info("  ... y " . (count($toCreate) - 30) . " más");
        }
        $this->info('');

        if ($this->option('dry-run')) {
            $this->info('Dry-run: no se aplicaron cambios.');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm("¿Crear " . count($toCreate) . " registros en la tabla transacciones?")) {
            $this->info('Cancelado.');
            return 0;
        }

        // Paso 3: Crear registros
        $this->info('Paso 3: Creando registros...');
        $created = 0;
        $errors = 0;
        $maxNumero = 0;

        foreach ($toCreate as $b) {
            try {
                $dt = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', "{$b['date']} {$b['time']}");

                Transaccion::create([
                    'user_id' => 1,
                    'dni_cliente' => '00000000',
                    'monto_transferencia' => $b['amount'],
                    'saldo_yape_momento' => 0,
                    'saldo_secundaria_momento' => 0,
                    'fecha_hora' => $dt,
                    'remitente_nombre' => $b['client'],
                    'tipo_movimiento' => 'yape_recibido',
                    'synced_from_email' => false,
                    'boleta_emitida' => true,
                    'boleta_emitida_at' => $b['date'],
                    'boleta_serie' => $b['serie'],
                    'boleta_numero' => $b['numero'],
                    'boleta_xml_path' => $b['xml_path'],
                    'boleta_cdr_path' => $b['cdr_path'],
                    'boleta_cdr_code' => $b['cdr_code'],
                    'boleta_estado_sunat' => $b['estado'],
                    'boleta_enviada_at' => $b['date'] . ' 23:59:59',
                ]);

                $this->line("  ✓ {$b['serie']}-{$b['numero']}");
                $created++;

                if ($b['numero'] > $maxNumero) {
                    $maxNumero = $b['numero'];
                }
            } catch (\Exception $e) {
                $this->error("  ✗ {$b['serie']}-{$b['numero']}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->info('');
        $this->info("  Creados: $created");
        $this->info("  Errores: $errors");

        // Paso 4: Actualizar correlativo
        if ($maxNumero > 0) {
            $this->info('');
            $this->info("Paso 4: Actualizando correlativo B001 a $maxNumero ...");
            try {
                $correlativo = CorrelativoBoleta::firstOrCreate(['serie' => 'B001']);
                $correlativo->ultimo_numero = $maxNumero;
                $correlativo->save();
                $this->info("  ✓ Correlativo B001 actualizado a $maxNumero");
            } catch (\Exception $e) {
                $this->error("  ✗ Error actualizando correlativo: " . $e->getMessage());
            }
        }

        $this->info('');
        $this->info('=== RECONSTRUCCIÓN COMPLETADA ===');

        return 0;
    }

    private function extractCdrCode(string $cdrPath): ?string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($cdrPath) === true) {
                $xmlContent = $zip->getFromIndex(0);
                $zip->close();

                if ($xmlContent) {
                    $dom = new \DOMDocument();
                    $dom->loadXML($xmlContent);
                    $xpath = new \DOMXPath($dom);
                    $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

                    $codeNode = $xpath->query('//cbc:ResponseCode')->item(0);
                    if ($codeNode) {
                        return trim($codeNode->nodeValue);
                    }
                }
            }
        } catch (\Exception $e) {
        }
        return null;
    }
}
