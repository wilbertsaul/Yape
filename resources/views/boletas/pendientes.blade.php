@extends('layouts.app')

@section('title', 'Boletas Pendientes')

@section('content')
<div class="row">
    <div class="col-12 mb-3">
        <h4 class="text-primary">
            <i class="bi bi-receipt"></i> Boletas Pendientes
        </h4>
        <p class="text-muted">Transacciones pendientes de emitir. La emisión envía automáticamente a SUNAT vía sendBill.</p>
    </div>
</div>

@if(session('boleta_error_raw'))
<div class="alert alert-danger">
    <strong>Respuesta del API:</strong>
    <pre class="mb-0 mt-1" style="font-size: 0.75rem;">{{ json_encode(session('boleta_error_raw'), JSON_PRETTY_PRINT) }}</pre>
</div>
@endif

<div class="card shadow-sm">
    <div class="card-header bg-white py-2 sticky-top" style="top: 56px; z-index: 100;">
        <div class="row align-items-center">
            <div class="col-auto">
                <span id="seleccionadas-text" class="text-muted small">0 seleccionadas</span>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-primary" id="btn-emitir-lote" disabled onclick="emitirLote()">
                    <i class="bi bi-send"></i> Emitir en lote
                </button>
            </div>
            <div class="col-auto">
                <a href="{{ route('boletas.emitidas') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-check2-all"></i> Ver Emitidas
                </a>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-3" style="width: 40px;"><input type="checkbox" id="seleccionar-todo"></th>
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
                        <td class="px-3">
                            <input type="checkbox" value="{{ $t->id }}" class="checkbox-item">
                        </td>
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
                                <form method="POST" action="{{ route('boletas.marcar-emitida', $t->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="bi bi-check-lg"></i> Emitir
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @if(!$t->cliente || !$t->cliente->dni)
                        <tr class="collapse" id="dni-{{ $t->id }}">
                            <td colspan="7" class="bg-light px-3 py-2">
                                <form method="POST" action="{{ route('boletas.marcar-emitida', $t->id) }}" class="row g-2 align-items-center">
                                    @csrf
                                    <div class="col-auto">
                                        <strong>DNI del cliente (opcional):</strong>
                                    </div>
                                    <div class="col-auto">
                                        <input type="text" name="dni" class="form-control form-control-sm" 
                                               placeholder="12345678" maxlength="8" pattern="[0-9]{0,8}"
                                               value="{{ $t->cliente->dni ?? '' }}">
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-lg"></i> Emitir
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @endif
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
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
</div>

<div class="mt-3 d-flex justify-content-between align-items-center">
    <div class="small text-muted">
        {{ $transacciones->total() }} transacciones
    </div>
    <div class="pagination-compact">
        {{ $transacciones->onEachSide(0)->links() }}
    </div>
</div>

@section('scripts')
<script>
function emitirLote() {
    const checked = document.querySelectorAll('.checkbox-item:checked');
    if (checked.length === 0) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route('boletas.emitir-lote') }}';
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}
document.getElementById('seleccionar-todo').addEventListener('change', function() {
    document.querySelectorAll('.checkbox-item').forEach(cb => cb.checked = this.checked);
    actualizarSeleccion();
});
document.querySelectorAll('.checkbox-item').forEach(cb => {
    cb.addEventListener('change', actualizarSeleccion);
});
function actualizarSeleccion() {
    const count = document.querySelectorAll('.checkbox-item:checked').length;
    const btn = document.getElementById('btn-emitir-lote');
    btn.disabled = count === 0;
    document.getElementById('seleccionadas-text').textContent = count + ' seleccionada' + (count !== 1 ? 's' : '');
}
</script>
<style>
.pagination-compact .pagination {
    margin-bottom: 0;
    gap: 2px;
}
.pagination-compact .page-link {
    padding: 0.15rem 0.45rem;
    font-size: 0.75rem;
    line-height: 1.2;
    border-radius: 4px;
}
.pagination-compact .page-link svg {
    display: block;
    width: 10px;
    height: 10px;
}
.pagination-compact .page-item.active .page-link {
    font-weight: 600;
}
</style>
@endsection
@endsection
