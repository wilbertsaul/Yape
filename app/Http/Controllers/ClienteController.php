<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Transaccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $clientes = Cliente::withCount('transacciones')->orderBy('nombre_completo')->paginate(20);
        return view('clientes.index', compact('clientes'));
    }

    public function store(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        $request->validate([
            'nombre_completo' => 'required|string|max:255',
            'dni' => 'nullable|string|size:8|unique:clientes,dni',
            'telefono' => 'nullable|string|max:20',
        ]);

        Cliente::create($request->only(['nombre_completo', 'dni', 'telefono']));

        return redirect()->route('clientes.index')->with('success', 'Cliente registrado.');
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        $cliente = Cliente::findOrFail($id);

        $request->validate([
            'nombre_completo' => 'required|string|max:255',
            'dni' => 'nullable|string|size:8|unique:clientes,dni,' . $id,
            'telefono' => 'nullable|string|max:20',
        ]);

        $cliente->update($request->only(['nombre_completo', 'dni', 'telefono']));

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado.');
    }

    public function destroy($id)
    {
        if (!Auth::user()->isAdmin()) {
            return back()->with('error', 'No tiene permisos.');
        }

        $cliente = Cliente::findOrFail($id);
        Transaccion::where('cliente_id', $id)->update(['cliente_id' => null]);
        $cliente->delete();

        return redirect()->route('clientes.index')->with('success', 'Cliente eliminado.');
    }
}
