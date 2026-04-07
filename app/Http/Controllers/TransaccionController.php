<?php

namespace App\Http\Controllers;

use App\Models\Transaccion;
use App\Models\SaldoInicial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransaccionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function obtenerSaldosActuales()
    {
        $ultimoSaldo = SaldoInicial::obtenerSaldos();
        
        if (!$ultimoSaldo) {
            return [
                'saldo_yape' => 0,
                'saldo_secundario' => 0,
            ];
        }

        $totalYape = Transaccion::where('tipo_movimiento', 'registro_yape')
            ->sum('monto_transferencia');
        $totalTraslados = Transaccion::where('tipo_movimiento', 'traslado_fondos')
            ->sum('monto_transferencia');

        return [
            'saldo_yape' => $ultimoSaldo->saldo_yape + $totalYape - $totalTraslados,
            'saldo_secundario' => $ultimoSaldo->saldo_secundario + $totalTraslados - $totalYape,
        ];
    }

    public function index()
    {
        $saldos = $this->obtenerSaldosActuales();
        
        $transacciones = Transaccion::with('user')
            ->orderBy('fecha_hora', 'desc')
            ->limit(50)
            ->get();

        return view('dashboard', compact('saldos', 'transacciones'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'dni_cliente' => 'required|string|size:8',
            'monto_transferencia' => 'required|numeric|min:0.01',
        ]);

        $saldos = $this->obtenerSaldosActuales();
        $monto = floatval($request->monto_transferencia);

        if ($saldos['saldo_yape'] < $monto) {
            return back()->with('error', 'Saldo insuficiente en Cuenta Yape. Debe realizar un traslado desde la Cuenta Secundaria.');
        }

        try {
            DB::transaction(function () use ($request, $saldos, $monto) {
                Transaccion::registrarYape(
                    Auth::id(),
                    $request->dni_cliente,
                    $monto,
                    $saldos['saldo_yape'] - $monto,
                    $saldos['saldo_secundario']
                );
            });

            return redirect()->route('dashboard')->with('success', 'Transacción registrada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al registrar la transacción.');
        }
    }

    public function traslado(Request $request)
    {
        $request->validate([
            'monto_traslado' => 'required|numeric|min:0.01',
        ]);

        $saldos = $this->obtenerSaldosActuales();
        $monto = floatval($request->monto_traslado);

        if ($saldos['saldo_secundario'] < $monto) {
            return back()->with('error', 'Saldo insuficiente en Cuenta Secundaria.');
        }

        try {
            DB::transaction(function () use ($saldos, $monto) {
                Transaccion::registrarTraslado(
                    Auth::id(),
                    $monto,
                    $saldos['saldo_yape'] + $monto,
                    $saldos['saldo_secundario'] - $monto
                );
            });

            return redirect()->route('dashboard')->with('success', 'Traslado de fondos realizado exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al realizar el traslado.');
        }
    }

    public function historial()
    {
        $transacciones = Transaccion::with('user')
            ->orderBy('fecha_hora', 'desc')
            ->paginate(20);

        return view('historial', compact('transacciones'));
    }

    public function destroy($id)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos para eliminar transacciones.');
        }

        try {
            $transaccion = Transaccion::findOrFail($id);
            $transaccion->delete();

            return redirect()->route('historial')->with('success', 'Transacción eliminada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar la transacción.');
        }
    }
}