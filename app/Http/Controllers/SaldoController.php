<?php

namespace App\Http\Controllers;

use App\Models\SaldoInicial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SaldoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $saldos = SaldoInicial::orderBy('fecha_actualizacion', 'desc')->get();
        $ultimo = SaldoInicial::obtenerSaldos();
        
        return view('saldos.index', compact('saldos', 'ultimo'));
    }

    public function update(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos para actualizar saldos.');
        }

        $request->validate([
            'saldo_yape' => 'required|numeric|min:0',
            'saldo_secundario' => 'required|numeric|min:0',
        ]);

        try {
            SaldoInicial::actualizarSaldos(
                floatval($request->saldo_yape),
                floatval($request->saldo_secundario)
            );

            return redirect()->route('saldos.index')->with('success', 'Saldos actualizados correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar los saldos.');
        }
    }
}