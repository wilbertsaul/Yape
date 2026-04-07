@extends('layouts.app')

@section('title', 'Historial de Transacciones')

@section('content')
<div class="row">
    <div class="col-12">
        <h4 class="text-primary mb-3">
            <i class="bi bi-clock-history"></i> Historial de Transacciones
        </h4>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-primary" style="background-color: #6C2BD9; color: white;">
                    <tr>
                        <th class="px-3">Fecha/Hora</th>
                        <th>Usuario</th>
                        <th>Tipo</th>
                        <th>DNI Cliente</th>
                        <th>Monto</th>
                        <th>Saldo Yape</th>
                        <th>Saldo Secundario</th>
                        @if(Auth::user()->isAdmin())
                        <th>Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($transacciones as $t)
                    <tr>
                        <td class="px-3">{{ $t->fecha_hora->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $t->user->name }}</td>
                        <td>
                            @if($t->tipo_movimiento == 'registro_yape')
                                <span class="badge bg-primary">Registro Yape</span>
                            @else
                                <span class="badge bg-warning text-dark">Traslado Fondos</span>
                            @endif
                        </td>
                        <td>{{ $t->dni_cliente }}</td>
                        <td class="{{ $t->tipo_movimiento == 'registro_yape' ? 'text-danger' : 'text-success' }}">
                            {{ $t->tipo_movimiento == 'registro_yape' ? '-' : '+' }}S/ {{ number_format($t->monto_transferencia, 2) }}
                        </td>
                        <td>S/ {{ number_format($t->saldo_yape_momento, 2) }}</td>
                        <td>S/ {{ number_format($t->saldo_secundaria_momento, 2) }}</td>
                        @if(Auth::user()->isAdmin())
                        <td>
                            <form action="{{ route('transaccion.destroy', $t->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" 
                                        onclick="return confirm('¿Eliminar esta transacción?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ Auth::user()->isAdmin() ? 8 : 7 }}" class="text-center text-muted py-4">
                            No hay transacciones registradas
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $transacciones->links() }}
</div>

<div class="mt-3">
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver al Dashboard
    </a>
</div>
@endsection