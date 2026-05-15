@extends('layouts.app')

@section('title', 'Boletas Emitidas')

@section('content')
<div class="row">
    <div class="col-12 mb-3">
        <h4 class="text-primary">
            <i class="bi bi-check2-all"></i> Boletas Emitidas
        </h4>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-3">Emitida</th>
                        <th>Fecha Op.</th>
                        <th>Cliente</th>
                        <th>DNI</th>
                        <th>Recibido</th>
                        <th>Comisión</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transacciones as $t)
                    <tr>
                        <td class="px-3">{{ $t->boleta_emitida_at->format('d/m H:i') }}</td>
                        <td>{{ $t->fecha_hora->format('d/m H:i') }}</td>
                        <td>{{ $t->remitente_nombre ?? $t->cliente?->nombre_completo ?? '-' }}</td>
                        <td>
                            @if($t->cliente && $t->cliente->dni)
                                <span class="badge bg-success">{{ $t->cliente->dni }}</span>
                            @else
                                <span class="badge bg-warning text-dark">Pendiente</span>
                            @endif
                        </td>
                        <td>S/ {{ number_format($t->monto_transferencia, 2) }}</td>
                        <td><strong>S/ {{ number_format($t->comision, 2) }}</strong></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Aún no has emitido ninguna boleta.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3 d-flex justify-content-between align-items-center">
    <a href="{{ route('boletas.pendientes') }}" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left"></i> Pendientes
    </a>
    {{ $transacciones->links() }}
</div>
@endsection
