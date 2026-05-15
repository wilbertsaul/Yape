<?php

namespace App\Http\Controllers;

use App\Models\Transaccion;
use App\Models\SaldoInicial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return [
            'saldo_yape' => $ultimoSaldo->saldo_yape,
            'saldo_secundario' => $ultimoSaldo->saldo_secundario,
        ];
    }

    public function index()
    {
        $saldos = $this->obtenerSaldosActuales();
        
        $transacciones = Transaccion::with('user')
            ->orderBy('fecha_hora', 'desc')
            ->limit(50)
            ->get();

        $boletasPendientes = Transaccion::where('tipo_movimiento', 'yape_recibido')
            ->where('boleta_emitida', false)
            ->count();

        $comisiones = Transaccion::whereNotNull('comision')->where('comision', '>', 0);
        $totalComisiones = (float) $comisiones->sum('comision');
        $comisionesPendientes = (float) Transaccion::where('tipo_movimiento', 'yape_recibido')
            ->where('boleta_emitida', false)
            ->whereNotNull('comision')
            ->sum('comision');
        $comisionesEmitidas = (float) Transaccion::where('tipo_movimiento', 'yape_recibido')
            ->where('boleta_emitida', true)
            ->whereNotNull('comision')
            ->sum('comision');

        return view('dashboard', compact(
            'saldos', 'transacciones', 'boletasPendientes',
            'totalComisiones', 'comisionesPendientes', 'comisionesEmitidas'
        ));
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