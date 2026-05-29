<?php

namespace App\Console\Commands;

use App\Models\Transaccion;
use App\Services\Sunat\GreenterFactory;
use App\Services\Sunat\SunatSender;
use Illuminate\Console\Command;

class EnviarBoletasSunat extends Command
{
    protected $signature = 'sunat:enviar-boletas
        {--id= : ID de transacción específica}
        {--force : Enviar aunque ya tengan ticket}
        {--generico : Forzar cliente_tipo=0 (sin DNI) para diagnóstico}';

    protected $description = 'Envía boletas pendientes a SUNAT vía Resumen Diario';

    public function handle(GreenterFactory $factory): int
    {
        $query = Transaccion::where('boleta_emitida', true)
            ->whereNull('boleta_cdr_code');

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        if (!$this->option('force')) {
            $query->whereNull('boleta_ticket');
        }

        $boletas = $query->orderBy('fecha_hora')->get();

        if ($boletas->isEmpty()) {
            $this->info('No hay boletas pendientes de envío.');
            return 0;
        }

        $this->info('Procesando ' . $boletas->count() . ' boleta(s)...');

        $see = $factory->create(true);
        $sender = new SunatSender($see);
        $serie = config('sunat.serie', 'EB01');

        $grupos = $boletas->groupBy(fn($b) => $b->fecha_hora->format('Y-m-d'));

        foreach ($grupos as $fecha => $grupo) {
            $this->line('Fecha: ' . $fecha . ' (' . $grupo->count() . ' boletas)');

            $ticketCorrelativo = 'RC-' . str_replace('-', '', $fecha);
            $correlativo = $this->getNextSummaryCorrelativo($ticketCorrelativo);

            $generico = $this->option('generico');
            $items = $grupo->map(fn($b) => [
                'serie' => $b->boleta_serie ?? $serie,
                'numero' => (string) $b->boleta_numero,
                'monto' => (float) ($b->comision ?? $b->monto_transferencia),
                'cliente_tipo' => $generico ? '0' : ($b->cliente && $b->cliente->dni ? '1' : '0'),
                'cliente_nro' => $generico ? '0' : ($b->cliente && $b->cliente->dni ? $b->cliente->dni : '0'),
            ])->toArray();

            $this->output->write('  Enviando a SUNAT... ');

            $result = $sender->enviarResumen($items, $correlativo, $fecha);

            if (!$result['success']) {
                $this->error('ERROR: ' . ($result['error'] ?? 'Desconocido'));
                if ($code = $result['code'] ?? null) {
                    $this->warn('Código: ' . $code);
                }
                if (!empty($result['soap_trace'])) {
                    $this->line('--- SOAP Request ---');
                    $this->line($result['soap_trace']['request'] ?? '(no capturado)');
                    $this->line('--- SOAP Response ---');
                    $this->line($result['soap_trace']['response'] ?? '(no capturado)');
                }
                $grupo->each->update(['boleta_estado_sunat' => 'ERROR']);
                continue;
            }

            $this->line('OK (ticket: ' . $result['ticket'] . ')');

            $xmlPath = 'sunat/xml/' . $ticketCorrelativo . '_' . $correlativo . '.xml';
            $xmlFull = storage_path('app/' . $xmlPath);
            if (!is_dir(dirname($xmlFull))) mkdir(dirname($xmlFull), 0755, true);
            file_put_contents($xmlFull, $result['xml']);

            $cdrPath = null;
            if (!empty($result['cdr_zip'])) {
                $cdrPath = 'sunat/cdr/R-' . $ticketCorrelativo . '_' . $correlativo . '.zip';
                $cdrFull = storage_path('app/' . $cdrPath);
                if (!is_dir(dirname($cdrFull))) mkdir(dirname($cdrFull), 0755, true);
                file_put_contents($cdrFull, $result['cdr_zip']);
            }

            $estado = 'PENDIENTE';
            if (isset($result['cdr_code'])) {
                $estado = (int)$result['cdr_code'] === 0 ? 'ACEPTADA' : 'RECHAZADA';
            }

            $updateData = [
                'boleta_ticket' => $result['ticket'],
                'boleta_xml_path' => $xmlPath,
                'boleta_estado_sunat' => $estado,
                'boleta_enviada_at' => now(),
            ];

            if (isset($result['cdr_code'])) {
                $updateData['boleta_cdr_code'] = (string) $result['cdr_code'];
                $updateData['boleta_cdr_path'] = $cdrPath;
                $updateData['boleta_cdr_notes'] = !empty($result['cdr_notes'])
                    ? implode("\n", $result['cdr_notes']) : null;
            }

            $grupo->each->update($updateData);

            $this->info('  ✓ Boleta(s) ' . $correlativo);
        }

        return 0;
    }

    private function getNextSummaryCorrelativo(string $key): string
    {
        $file = storage_path('app/sunat/summary_counter.json');
        $counters = [];
        if (file_exists($file)) {
            $counters = json_decode(file_get_contents($file), true) ?? [];
        }

        $counters[$key] = ($counters[$key] ?? 0) + 1;
        file_put_contents($file, json_encode($counters));

        return str_pad((string) $counters[$key], 3, '0', STR_PAD_LEFT);
    }
}
