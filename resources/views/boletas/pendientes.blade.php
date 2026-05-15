@extends('layouts.app')

@section('title', 'Boletas Pendientes')

@section('content')
<div class="row">
    <div class="col-12 mb-3">
        <h4 class="text-primary">
            <i class="bi bi-receipt"></i> Boletas Pendientes
        </h4>
        <p class="text-muted">Yapes recibidos que aún no has emitido la boleta. Revisa y completa los DNIs faltantes.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-3">Fecha</th>
                        <th>Cliente</th>
                        <th>DNI</th>
                        <th>Recibido</th>
                        <th>Comisión</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transacciones as $t)
                    <tr>
                        <td class="px-3">{{ $t->fecha_hora->format('d/m H:i') }}</td>
                        <td>{{ $t->remitente_nombre ?? $t->cliente?->nombre_completo ?? '-' }}</td>
                        <td>
                            @if($t->cliente && $t->cliente->dni)
                                <span class="badge bg-success">{{ $t->cliente->dni }}</span>
                            @else
                                <span class="badge bg-warning text-dark" role="button" data-bs-toggle="collapse" data-bs-target="#dni-{{ $t->id }}">
                                    <i class="bi bi-pencil"></i> Agregar DNI
                                </span>
                            @endif
                        </td>
                        <td>S/ {{ number_format($t->monto_transferencia, 2) }}</td>
                        <td>
                            <form method="POST" action="{{ route('boletas.actualizar-comision', $t->id) }}" class="d-inline">
                                @csrf
                                <div class="input-group input-group-sm" style="max-width: 120px;">
                                    <span class="input-group-text">S/</span>
                                    <input type="number" step="0.01" min="0" name="comision" 
                                           value="{{ number_format($t->comision, 2) }}" 
                                           class="form-control form-control-sm" 
                                           onchange="this.form.submit()">
                                </div>
                            </form>
                        </td>
                        <td>
                            @if($t->cliente && $t->cliente->dni)
                            <form method="POST" action="{{ route('boletas.marcar-emitida', $t->id) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="bi bi-check-lg"></i> Emitida
                                </button>
                            </form>
                            @else
                            <button class="btn btn-sm btn-outline-secondary" disabled>
                                <i class="bi bi-check-lg"></i> Sin DNI
                            </button>
                            @endif
                        </td>
                    </tr>
                    @if(!$t->cliente || !$t->cliente->dni)
                    <tr class="collapse" id="dni-{{ $t->id }}">
                        <td colspan="6" class="bg-light px-3 py-2">
                            <form method="POST" action="{{ route('boletas.marcar-emitida', $t->id) }}" class="row g-2 align-items-center">
                                @csrf
                                <div class="col-auto">
                                    <strong>DNI del cliente:</strong>
                                </div>
                                <div class="col-auto">
                                    <input type="text" name="dni" class="form-control form-control-sm" 
                                           placeholder="12345678" maxlength="8" pattern="[0-9]{8}" required
                                           value="{{ $t->cliente->dni ?? '' }}">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="bi bi-check-lg"></i> Guardar DNI y Marcar Emitida
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-emoji-smile fs-3 d-block mb-2"></i>
                            No hay boletas pendientes. Todas las transacciones están al día.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3 d-flex justify-content-between align-items-center">
    <a href="{{ route('boletas.emitidas') }}" class="btn btn-outline-secondary">
        <i class="bi bi-check2-all"></i> Ver Emitidas
    </a>
    {{ $transacciones->links() }}
</div>
@endsection
