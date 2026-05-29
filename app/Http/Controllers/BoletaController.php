<?php

namespace App\Http\Controllers;

use App\Models\CorrelativoBoleta;
use App\Models\Transaccion;
use Greenter\Model\Client\Client;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BoletaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function pendientes()
    {
        $transacciones = Transaccion::with('cliente')
            ->where('boleta_emitida', false)
            ->where('tipo_movimiento', '!=', 'traslado_fondos')
            ->orderBy('fecha_hora', 'desc')
            ->paginate(20);

        return view('boletas.pendientes', compact('transacciones'));
    }

    public function emitidas()
    {
        $transacciones = Transaccion::with('cliente')
            ->where('boleta_emitida', true)
            ->where('tipo_movimiento', '!=', 'traslado_fondos')
            ->orderBy('boleta_emitida_at', 'desc')
            ->paginate(20);

        return view('boletas.emitidas', compact('transacciones'));
    }

    public function marcarEmitida(Request $request, $id)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        set_time_limit(120);

        $tx = Transaccion::findOrFail($id);

        if ($tx->tipo_movimiento === 'traslado_fondos') {
            return back()->with('error', 'No se pueden emitir boletas para transferencias entre cuentas.');
        }

        if ($request->has('dni') && $request->dni) {
            $request->validate(['dni' => 'required|string|size:8']);
            if (!$tx->cliente) {
                $cliente = \App\Models\Cliente::create([
                    'nombre_completo' => $tx->remitente_nombre ?? 'Cliente',
                    'dni' => $request->dni,
                ]);
                $tx->cliente_id = $cliente->id;
            } else {
                $tx->cliente->update(['dni' => $request->dni]);
            }
        }

        if ($request->has('comision')) {
            $request->validate(['comision' => 'required|numeric|min:0']);
            $tx->comision = floatval($request->comision);
        }

        $serie = config('sunat.serie', 'EB01');
        $correlativo = CorrelativoBoleta::firstOrCreate(['serie' => $serie]);
        $correlativo->increment('ultimo_numero');
        $correlativo->refresh();

        $tx->boleta_serie = $serie;
        $tx->boleta_numero = $correlativo->ultimo_numero;
        $tx->boleta_emitida = true;
        $tx->boleta_emitida_at = now();
        $tx->save();

        $resultado = $this->enviarBoletaSunat($tx);

        if ($resultado['success']) {
            $estado = $resultado['estado'];
            return back()->with('success', "Boleta {$serie}-{$tx->boleta_numero} emitida y enviada a SUNAT. Estado: {$estado}");
        }

        return back()->with('warning', "Boleta {$serie}-{$tx->boleta_numero} emitida pero error al enviar a SUNAT: {$resultado['error']}. Puedes reintentar desde Boletas Emitidas.");
    }

    public function actualizarComision(Request $request, $id)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        $request->validate(['comision' => 'required|numeric|min:0']);

        $tx = Transaccion::findOrFail($id);
        $tx->comision = floatval($request->comision);
        $tx->save();

        return back()->with('success', 'Comisión actualizada.');
    }

    public function enviarSunat(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        $generico = $request->query('generico') === '1';
        if ($generico) {
            Log::warning('Enviando con cliente_tipo=0 (genérico) para diagnóstico');
        }

        $query = Transaccion::where('boleta_emitida', true)
            ->whereNull('boleta_cdr_code')
            ->whereNotNull('boleta_serie')
            ->whereNotNull('boleta_numero');

        $boletas = $query->orderBy('fecha_hora')->get();

        if ($boletas->isEmpty()) {
            return back()->with('info', 'No hay boletas pendientes de envío a SUNAT.');
        }

        try {
            $factory = app(\App\Services\Sunat\GreenterFactory::class);
            $see = $factory->create(true);
            $sender = new \App\Services\Sunat\SunatSender($see);
            $serie = config('sunat.serie', 'EB01');

            $grupos = $boletas->groupBy(fn($b) => $b->fecha_hora->format('Y-m-d'));
            $totalExito = 0;
            $totalError = 0;

            foreach ($grupos as $fecha => $grupo) {
                $ticketCorrelativo = 'RC-' . str_replace('-', '', $fecha);
                $correlativo = $this->getNextSummaryCorrelativo($ticketCorrelativo);

                $items = $grupo->map(fn($b) => [
                    'serie' => $b->boleta_serie ?? $serie,
                    'numero' => (string) $b->boleta_numero,
                    'monto' => (float) ($b->comision ?? $b->monto_transferencia),
                    'cliente_tipo' => $generico ? '0' : ($b->cliente && $b->cliente->dni ? '1' : '0'),
                    'cliente_nro' => $generico ? '0' : ($b->cliente && $b->cliente->dni ? $b->cliente->dni : '0'),
                ])->toArray();

                $result = $sender->enviarResumen($items, $correlativo, $fecha);

                if (!$result['success']) {
                    $grupo->each->update(['boleta_estado_sunat' => 'ERROR']);
                    $totalError += $grupo->count();
                    if (!empty($result['soap_trace'])) {
                        Log::error('SUNAT SOAP trace on error', $result['soap_trace']);
                    }
                    session()->flash('boleta_error_raw', $result);
                    continue;
                }

                $xmlPath = 'sunat/xml/' . $ticketCorrelativo . '_' . $correlativo . '.xml';
                $xmlFull = storage_path('app/' . $xmlPath);
                if (!is_dir(dirname($xmlFull))) mkdir(dirname($xmlFull), 0755, true);
                file_put_contents($xmlFull, $result['xml']);

                $grupo->each->update([
                    'boleta_ticket' => $result['ticket'],
                    'boleta_xml_path' => $xmlPath,
                    'boleta_estado_sunat' => 'PENDIENTE',
                    'boleta_enviada_at' => now(),
                ]);

                $totalExito += $grupo->count();
            }

            $msg = "Envío a SUNAT completado: $totalExito exitosa(s)";
            if ($totalError > 0) $msg .= ", $totalError con error(es)";
            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Error al enviar a SUNAT: ' . $e->getMessage());
        }
    }

    public function enviarIndividualSunat($id)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        $tx = Transaccion::findOrFail($id);

        if (!$tx->boleta_emitida) {
            return back()->with('error', 'La boleta no está emitida.');
        }

        try {
            $factory = app(\App\Services\Sunat\GreenterFactory::class);
            $see = $factory->create(true);

            $builder = app(\App\Services\Sunat\BoletaDataBuilder::class);
            $correlativo = (int) $tx->boleta_numero;
            $invoice = $builder->build($tx, $correlativo, $tx->boleta_serie, $correlativo);

            $result = $see->send($invoice);
            $xml = $see->getFactory()->getLastXml();

            if (!$result->isSuccess()) {
                $error = $result->getError();
                Log::error('SUNAT sendBill individual error', [
                    'id' => $id,
                    'boleta' => "{$tx->boleta_serie}-{$tx->boleta_numero}",
                    'error' => $error->getMessage(),
                    'code' => $error->getCode(),
                ]);
                return back()->with('error', 'Error SUNAT: ' . $error->getMessage() . ' (código ' . $error->getCode() . ')');
            }

            $cdr = $result->getCdrResponse();

            $xmlPath = 'sunat/xml/BILL-' . $tx->boleta_serie . '-' . $tx->boleta_numero . '.xml';
            $xmlFull = storage_path('app/' . $xmlPath);
            if (!is_dir(dirname($xmlFull))) mkdir(dirname($xmlFull), 0755, true);
            file_put_contents($xmlFull, $xml);

            $cdrPath = null;
            try {
                $cdrZip = $result->getCdrZip();
                if ($cdrZip) {
                    $cdrPath = 'sunat/cdr/R-BILL-' . $tx->boleta_serie . '-' . $tx->boleta_numero . '.zip';
                    $cdrFull = storage_path('app/' . $cdrPath);
                    if (!is_dir(dirname($cdrFull))) mkdir(dirname($cdrFull), 0755, true);
                    file_put_contents($cdrFull, $cdrZip);
                }
            } catch (\Throwable $e) {}

            $estado = $cdr && (int)$cdr->getCode() === 0 ? 'ACEPTADA' : 'RECHAZADA';

            $tx->update([
                'boleta_xml_path' => $xmlPath,
                'boleta_cdr_path' => $cdrPath,
                'boleta_cdr_code' => (string) ($cdr ? $cdr->getCode() : ''),
                'boleta_cdr_notes' => $cdr ? implode("\n", $cdr->getNotes() ?? []) : null,
                'boleta_estado_sunat' => $estado,
                'boleta_emitida_at' => now(),
                'boleta_enviada_at' => now(),
            ]);

            return back()->with('success', "Boleta {$tx->boleta_serie}-{$tx->boleta_numero}: {$estado}" . ($cdr ? " (código {$cdr->getCode()})" : ''));

        } catch (\Exception $e) {
            Log::error('SUNAT sendBill individual exception: ' . $e->getMessage());
            return back()->with('error', 'Error al enviar: ' . $e->getMessage());
        }
    }

    public function anularBoleta($id)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        set_time_limit(120);

        $tx = Transaccion::findOrFail($id);

        if (!$tx->boleta_emitida || !$tx->boleta_serie || !$tx->boleta_numero) {
            return back()->with('error', 'Esta transacción no tiene una boleta emitida.');
        }

        $monto = (float) ($tx->comision ?? $tx->monto_transferencia);
        $docId = $tx->boleta_serie . '-' . $tx->boleta_numero;

        try {
            $factory = app(\App\Services\Sunat\GreenterFactory::class);
            $see = $factory->create(true);

            $ncSerie = config('sunat.serie_nc', 'BNC1');
            $correlativo = CorrelativoBoleta::firstOrCreate(['serie' => $ncSerie]);
            $correlativo->increment('ultimo_numero');
            $correlativo->refresh();

            $ncNumero = str_pad((string) $correlativo->ultimo_numero, 8, '0', STR_PAD_LEFT);

            $client = new Client();
            $client->setTipoDoc('0')
                   ->setNumDoc('0')
                   ->setRznSocial('CLIENTES VARIOS');

            $company = $this->buildCompany();

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
                ->setMtoOperExoneradas($monto)
                ->setMtoIGV(0)
                ->setMtoOperGravadas(0)
                ->setMtoOperInafectas(0)
                ->setTotalImpuestos(0)
                ->setValorVenta($monto)
                ->setSubTotal($monto)
                ->setMtoImpVenta($monto);

            $detail = new SaleDetail();
            $detail->setCodProducto('YAPE001')
                   ->setUnidad('ZZ')
                   ->setCantidad(1)
                   ->setDescripcion('Anulacion boleta ' . $docId)
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
                   ->setValue(\App\Services\NumeroALetras::convertir($monto));

            $note->setDetails([$detail])->setLegends([$legend]);

            $result = $see->send($note);

            $xml = $see->getFactory()->getLastXml();
            $xmlPath = 'sunat/xml/NC-' . $ncSerie . '-' . $ncNumero . '.xml';
            $xmlFull = storage_path('app/' . $xmlPath);
            if (!is_dir(dirname($xmlFull))) {
                mkdir(dirname($xmlFull), 0755, true);
            }
            file_put_contents($xmlFull, $xml);

            if (!$result->isSuccess()) {
                $error = $result->getError();
                $tx->update([
                    'boleta_estado_sunat' => 'ERROR_ANULACION',
                    'boleta_cdr_code' => (string) $error->getCode(),
                    'boleta_cdr_notes' => $error->getMessage(),
                ]);
                return back()->with('error', "Error al enviar Nota de Crédito a SUNAT: {$error->getMessage()} (código {$error->getCode()})");
            }

            $cdr = $result->getCdrResponse();
            $cdrCode = $cdr ? (int) $cdr->getCode() : -1;
            $cdrDesc = $cdr ? $cdr->getDescription() : '';

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

            $tx->update([
                'boleta_estado_sunat' => $cdrCode === 0 ? 'ANULADO' : 'ERROR_ANULACION',
                'boleta_ticket' => 'NC:' . $ncSerie . '-' . $correlativo->ultimo_numero,
                'boleta_cdr_code' => (string) $cdrCode,
                'boleta_cdr_notes' => $cdrDesc,
                'boleta_cdr_path' => $cdrPath,
                'boleta_xml_path' => $xmlPath,
                'boleta_enviada_at' => now(),
            ]);

            if ($cdrCode === 0) {
                return back()->with('success', "Boleta {$docId} anulada exitosamente vía Nota de Crédito {$ncSerie}-{$correlativo->ultimo_numero}.");
            }

            $notas = $cdr ? implode(', ', $cdr->getNotes() ?? []) : '';
            $detalleNotas = $notas ? " - {$notas}" : '';
            return back()->with('error', "Nota de Crédito rechazada por SUNAT (código {$cdrCode}): {$cdrDesc}{$detalleNotas}");
        } catch (\Exception $e) {
            Log::error('Error en anularBoleta: ' . $e->getMessage());
            return back()->with('error', 'Excepción al anular boleta: ' . $e->getMessage());
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

    private function enviarBoletaSunat(Transaccion $tx): array
    {
        try {
            $factory = app(\App\Services\Sunat\GreenterFactory::class);
            $see = $factory->create(true);
            $builder = app(\App\Services\Sunat\BoletaDataBuilder::class);
            $invoice = $builder->build($tx, (int) $tx->boleta_numero, $tx->boleta_serie, (int) $tx->boleta_numero);

            $result = $see->send($invoice);
            $xml = $see->getFactory()->getLastXml();

            if (!$result->isSuccess()) {
                $error = $result->getError();
                Log::error('SUNAT sendBill direct error', [
                    'id' => $tx->id,
                    'boleta' => $tx->boleta_serie . '-' . $tx->boleta_numero,
                    'error' => $error->getMessage(),
                ]);
                return ['success' => false, 'error' => $error->getMessage()];
            }

            $cdr = $result->getCdrResponse();

            $xmlPath = 'sunat/xml/BILL-' . $tx->boleta_serie . '-' . $tx->boleta_numero . '.xml';
            $xmlFull = storage_path('app/' . $xmlPath);
            if (!is_dir(dirname($xmlFull))) mkdir(dirname($xmlFull), 0755, true);
            file_put_contents($xmlFull, $xml);

            $cdrPath = null;
            try {
                $cdrZip = $result->getCdrZip();
                if ($cdrZip) {
                    $cdrPath = 'sunat/cdr/R-BILL-' . $tx->boleta_serie . '-' . $tx->boleta_numero . '.zip';
                    $cdrFull = storage_path('app/' . $cdrPath);
                    if (!is_dir(dirname($cdrFull))) mkdir(dirname($cdrFull), 0755, true);
                    file_put_contents($cdrFull, $cdrZip);
                }
            } catch (\Throwable $e) {}

            $estado = $cdr && (int)$cdr->getCode() === 0 ? 'ACEPTADA' : 'RECHAZADA';

            $tx->update([
                'boleta_xml_path' => $xmlPath,
                'boleta_cdr_path' => $cdrPath,
                'boleta_cdr_code' => (string) $cdr->getCode(),
                'boleta_estado_sunat' => $estado,
                'boleta_enviada_at' => now(),
            ]);

            return ['success' => true, 'estado' => $estado, 'cdr_code' => (int) $cdr->getCode()];
        } catch (\Exception $e) {
            Log::error('SUNAT sendBill direct exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function consultarSunat()
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        set_time_limit(120);

        $query = Transaccion::where('boleta_emitida', true)
            ->where('boleta_estado_sunat', 'PENDIENTE')
            ->whereNull('boleta_cdr_code')
            ->whereNotNull('boleta_serie')
            ->whereNotNull('boleta_numero');

        $boletas = $query->orderBy('fecha_hora')->get();

        if ($boletas->isEmpty()) {
            return back()->with('info', 'No hay boletas pendientes de consulta.');
        }

        $errores = [];

        try {
            $factory = app(\App\Services\Sunat\GreenterFactory::class);
            $consultCdr = $factory->createConsultService();
            $sender = new \App\Services\Sunat\SunatSender(
                $factory->create(true),
                $consultCdr
            );
            $actualizados = 0;

            foreach ($boletas as $tx) {
                $docResult = $sender->consultarPorDocumento(
                    config('sunat.ruc'),
                    '03',
                    $tx->boleta_serie,
                    (int) $tx->boleta_numero
                );

                if (!$docResult['success']) {
                    $errores[] = "{$tx->boleta_serie}-{$tx->boleta_numero}: {$docResult['error']}";
                    continue;
                }

                if (!empty($docResult['pending'])) {
                    continue;
                }

                $this->saveCdrResult(
                    collect([$tx]),
                    $docResult,
                    'DOC-' . $tx->boleta_serie . '-' . $tx->boleta_numero
                );
                $actualizados++;
            }

            $msg = '';
            if ($actualizados > 0) {
                $msg = "$actualizados boleta(s) actualizada(s) con CDR de SUNAT.";
            }
            if (!empty($errores)) {
                $msg .= ($msg ? ' | ' : '') . 'Errores: ' . implode(' | ', $errores);
            }
            if (empty($msg)) {
                $msg = 'SUNAT aún no ha procesado los comprobantes. Intente de nuevo en unos minutos.';
            }
            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Error al consultar SUNAT: ' . $e->getMessage());
        }
    }

    public function consultarAnulacion($id)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        $tx = Transaccion::find($id);
        if (!$tx || $tx->boleta_estado_sunat !== 'ANULADO' || !$tx->boleta_ticket) {
            return back()->with('error', 'La boleta no está en estado de anulación pendiente.');
        }

        try {
            $factory = app(\App\Services\Sunat\GreenterFactory::class);
            $see = $factory->create(true);
            $sender = new \App\Services\Sunat\SunatSender($see);

            $result = $sender->consultarTicket($tx->boleta_ticket);

            if (!empty($result['pending'])) {
                $tx->boleta_estado_sunat = 'ANULADO';
                $tx->save();
                return back()->with('info', 'SUNAT aún no ha procesado la anulación. Intente de nuevo en unos minutos.');
            }

            if (!$result['success']) {
                return back()->with('error', 'Error al consultar anulación: ' . ($result['error'] ?? '?'));
            }

            $cdrCode = (int) $result['cdr_code'];

            if ($cdrCode === 0) {
                $tx->boleta_emitida = false;
                $tx->boleta_serie = null;
                $tx->boleta_numero = null;
                $tx->boleta_ticket = null;
                $tx->boleta_estado_sunat = null;
                $tx->boleta_cdr_code = null;
                $tx->boleta_cdr_path = null;
                $tx->boleta_xml_path = null;
                $tx->boleta_emitida_at = null;
                $tx->boleta_enviada_at = null;
                $tx->boleta_respuesta_raw = null;
                $tx->save();

                return back()->with('success', 'Anulación procesada por SUNAT. La boleta ha sido reseteada y está lista para re-emitir.');
            }

            $tx->boleta_estado_sunat = 'ERROR_ANULACION';
            $tx->boleta_cdr_code = (string) $cdrCode;
            $tx->save();

            return back()->with('error', "Anulación rechazada por SUNAT (código $cdrCode).");
        } catch (\Exception $e) {
            Log::error('SUNAT consultarAnulacion exception: ' . $e->getMessage());
            return back()->with('error', 'Error al consultar anulación: ' . $e->getMessage());
        }
    }

    private function saveCdrResult($txs, array $result, string $ticket): void
    {
        $cdrPath = null;
        if (!empty($result['cdr_zip'])) {
            $cdrPath = 'sunat/cdr/R-' . $ticket . '.zip';
            $cdrFull = storage_path('app/' . $cdrPath);
            if (!is_dir(dirname($cdrFull))) mkdir(dirname($cdrFull), 0755, true);
            file_put_contents($cdrFull, $result['cdr_zip']);
        }

        $estado = (int)$result['cdr_code'] === 0 ? 'ACEPTADA' : 'RECHAZADA';
        $txs->each->update([
            'boleta_cdr_path' => $cdrPath,
            'boleta_cdr_code' => (string) $result['cdr_code'],
            'boleta_cdr_notes' => !empty($result['cdr_notes'])
                ? implode("\n", $result['cdr_notes']) : null,
            'boleta_estado_sunat' => $estado,
        ]);
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

    public function downloadSunatFile(string $type, string $filename)
    {
        $path = storage_path("app/sunat/$type/" . basename($filename));
        if (!file_exists($path)) {
            return back()->with('error', 'Archivo no encontrado.');
        }
        return response()->download($path);
    }

    public function reintentarBoleta($id)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        $tx = Transaccion::findOrFail($id);

        if (!$tx->boleta_emitida) {
            return back()->with('error', 'La boleta no está emitida.');
        }

        $tx->update([
            'boleta_estado_sunat' => null,
            'boleta_ticket' => null,
            'boleta_xml_path' => null,
            'boleta_cdr_path' => null,
            'boleta_cdr_code' => null,
            'boleta_cdr_notes' => null,
            'boleta_enviada_at' => null,
        ]);

        return back()->with('success', "Boleta {$tx->boleta_serie}-{$tx->boleta_numero} reseteada. Ahora puede corregir el DNI y presionar 'Enviar a SUNAT'.");
    }

    public function editarDni(Request $request, $id)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        $request->validate(['dni' => 'required|string|size:8']);

        $tx = Transaccion::findOrFail($id);

        if (!$tx->cliente) {
            $cliente = \App\Models\Cliente::create([
                'nombre_completo' => $tx->remitente_nombre ?? 'Cliente',
                'dni' => $request->dni,
            ]);
            $tx->cliente_id = $cliente->id;
        } else {
            $tx->cliente->update(['dni' => $request->dni]);
        }

        return back()->with('success', 'DNI actualizado a ' . $request->dni . '.');
    }

    public function emitirLote(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        set_time_limit(120);

        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return back()->with('error', 'Seleccione al menos una boleta.');
        }

        $transacciones = Transaccion::with('cliente')
            ->whereIn('id', $ids)
            ->where('boleta_emitida', false)
            ->where('tipo_movimiento', '!=', 'traslado_fondos')
            ->get();

        $serie = config('sunat.serie', 'EB01');
        $correlativo = CorrelativoBoleta::firstOrCreate(['serie' => $serie]);

        $count = 0;
        $numeros = [];
        $errores = [];
        $exitos = [];
        foreach ($transacciones as $tx) {
            $correlativo->increment('ultimo_numero');
            $correlativo->refresh();

            $tx->boleta_serie = $serie;
            $tx->boleta_numero = $correlativo->ultimo_numero;
            $tx->boleta_emitida = true;
            $tx->boleta_emitida_at = now();
            $tx->save();
            $numeros[] = $correlativo->ultimo_numero;

            $resultado = $this->enviarBoletaSunat($tx);
            if ($resultado['success']) {
                $exitos[] = $correlativo->ultimo_numero;
            } else {
                $errores[] = $correlativo->ultimo_numero . ' (' . $resultado['error'] . ')';
            }
            $count++;
        }

        $detalle = $count > 0 ? ' (' . implode(', ', $numeros) . ')' : '';
        $msg = "$count boleta(s) emitida(s) en lote.$detalle";
        if (!empty($exitos)) $msg .= " Enviadas a SUNAT: " . implode(', ', $exitos);
        if (!empty($errores)) $msg .= " Con error: " . implode('; ', $errores) . '. Puedes reintentar desde Boletas Emitidas.';
        return back()->with('success', $msg);
    }
}
