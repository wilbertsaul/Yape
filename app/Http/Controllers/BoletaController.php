<?php

namespace App\Http\Controllers;

use App\Models\Transaccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BoletaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function pendientes()
    {
        $transacciones = Transaccion::with('cliente')
            ->where('tipo_movimiento', 'yape_recibido')
            ->where('boleta_emitida', false)
            ->orderBy('fecha_hora', 'desc')
            ->paginate(20);

        return view('boletas.pendientes', compact('transacciones'));
    }

    public function emitidas()
    {
        $transacciones = Transaccion::with('cliente')
            ->where('tipo_movimiento', 'yape_recibido')
            ->where('boleta_emitida', true)
            ->orderBy('boleta_emitida_at', 'desc')
            ->paginate(20);

        return view('boletas.emitidas', compact('transacciones'));
    }

    public function marcarEmitida(Request $request, $id)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        $tx = Transaccion::findOrFail($id);

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

        $tx->boleta_emitida = true;
        $tx->boleta_emitida_at = now();
        $tx->save();

        return back()->with('success', 'Boleta marcada como emitida.');
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
}
