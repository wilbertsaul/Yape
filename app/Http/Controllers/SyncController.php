<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class SyncController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function manual(Request $request)
    {
        $force = $request->has('force');

        if (!$force) {
            $lastSync = session('last_sync_attempt');
            if ($lastSync && $lastSync->diffInMinutes(now()) < 5) {
                $wait = 5 - $lastSync->diffInMinutes(now());
                return back()->with('error', "Debes esperar {$wait} minuto(s) entre sincronizaciones. Usa 'Forzar' si es urgente.");
            }
        }

        session(['last_sync_attempt' => now()]);

        Artisan::call('sync:yape');

        $output = Artisan::output();

        $nuevos = 0;
        $duplicados = 0;
        $errores = 0;
        $noClasificados = 0;

        if (preg_match('/Nuevos registros:\s*(\d+)/', $output, $m)) $nuevos = (int)$m[1];
        if (preg_match('/Duplicados omitidos:\s*(\d+)/', $output, $m)) $duplicados = (int)$m[1];
        if (preg_match('/No clasificados:\s*(\d+)/', $output, $m)) $noClasificados = (int)$m[1];
        if (preg_match('/Errores:\s*(\d+)/', $output, $m)) $errores = (int)$m[1];

        $mensaje = "Sincronización completada: {$nuevos} nuevos, {$duplicados} duplicados, {$noClasificados} no clasificados, {$errores} errores.";

        if ($nuevos > 0) {
            return back()->with('success', $mensaje);
        }

        return back()->with('info', $mensaje);
    }
}
