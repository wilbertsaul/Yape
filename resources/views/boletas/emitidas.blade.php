@extends('layouts.app')

@section('title', 'Boletas Emitidas')

@section('content')
<div class="page-heading">
    <div>
        <h4 class="text-primary">
            <i class="bi bi-check2-all"></i> Boletas Emitidas
        </h4>
        <p class="text-muted mb-0">Boletas ya emitidas. Puedes enviarlas a SUNAT o descargar los comprobantes.</p>
    </div>
    <a href="{{ route('boletas.pendientes') }}" class="btn btn-outline-primary btn-sm mobile-full">
        <i class="bi bi-arrow-left"></i> Pendientes
    </a>
</div>

@if(session('boleta_error_raw'))
<div class="alert alert-danger">
    <strong>Error al enviar a SUNAT:</strong>
    <pre class="mb-0 mt-1 text-break-mobile" style="font-size: 0.75rem;">{{ json_encode(session('boleta_error_raw'), JSON_PRETTY_PRINT) }}</pre>
</div>
@endif

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 table-card-list">
                <thead class="table-light">
                    <tr>
                        <th class="px-3">Emitida</th>
                        <th>Boleta</th>
                        <th>Fecha Op.</th>
                        <th>Cliente</th>
                        <th>DNI</th>
                        <th>Recibido</th>
                        <th>Comision</th>
                        <th>Estado SUNAT</th>
                        <th>Descargas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transacciones as $t)
                    <tr>
                        <td class="px-3">
                            <span class="cell-label">Emitida</span>
                            {{ $t->boleta_emitida_at->format('d/m H:i') }}
                        </td>
                        <td>
                            <span class="cell-label">Boleta</span>
                            <span class="badge bg-primary">
                                {{ $t->boleta_serie ?? '???' }}-{{ $t->boleta_numero ? str_pad($t->boleta_numero, 4, '0', STR_PAD_LEFT) : '0000' }}
                            </span>
                        </td>
                        <td>
                            <span class="cell-label">Fecha Op.</span>
                            {{ $t->fecha_hora->format('d/m H:i') }}
                        </td>
                        <td class="text-break-mobile">
                            <span class="cell-label">Cliente</span>
                            {{ $t->remitente_nombre ?? $t->cliente?->nombre_completo ?? '-' }}
                        </td>
                        <td>
                            <span class="cell-label">DNI</span>
                            <div class="d-flex align-items-center gap-1">
                                @if($t->cliente && $t->cliente->dni)
                                    <span class="badge bg-success">{{ $t->cliente->dni }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                @endif
                                <button class="btn btn-sm btn-outline-secondary py-0" onclick="editarDni({{ $t->id }}, '{{ $t->cliente?->dni ?? '' }}')" title="Editar DNI">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <span class="cell-label">Recibido</span>
                            S/ {{ number_format($t->monto_transferencia, 2) }}
                        </td>
                        <td>
                            <span class="cell-label">Comision</span>
                            <strong>S/ {{ number_format($t->comision, 2) }}</strong>
                        </td>
                        <td class="text-break-mobile">
                            <span class="cell-label">Estado SUNAT</span>
                            @if(($t->boleta_cdr_code === null || $t->boleta_cdr_code === '') && $t->boleta_ticket)
                                <span class="badge bg-info text-dark">Enviado</span>
                                <br><small class="text-muted">{{ $t->boleta_ticket }}</small>
                            @elseif($t->boleta_cdr_code === null || $t->boleta_cdr_code === '')
                                <span class="badge bg-secondary">Pendiente</span>
                            @elseif($t->boleta_estado_sunat === 'ACEPTADA')
                                <span class="badge bg-success">{{ $t->boleta_estado_sunat }}</span>
                            @elseif($t->boleta_estado_sunat === 'RECHAZADA')
                                <span class="badge bg-danger">{{ $t->boleta_estado_sunat }}</span>
                            @elseif($t->boleta_estado_sunat === 'ERROR_ANULACION')
                                <span class="badge bg-danger">{{ $t->boleta_estado_sunat }}</span>
                            @elseif($t->boleta_estado_sunat === 'ANULADO')
                                <span class="badge bg-warning text-dark">{{ $t->boleta_estado_sunat }}</span>
                                <br><small class="text-muted">Ticket: {{ $t->boleta_ticket }}</small>
                            @else
                                <span class="badge bg-warning text-dark">{{ $t->boleta_estado_sunat ?? '?' }}</span>
                            @endif
                            @if($t->boleta_cdr_code !== null && $t->boleta_cdr_code !== '')
                                <br><small class="text-muted">Codigo: {{ $t->boleta_cdr_code }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="cell-label">Descargas</span>
                            <div class="mobile-actions">
                                @if($t->boleta_xml_path)
                                    <a href="{{ route('sunat.download', ['type' => 'xml', 'filename' => basename($t->boleta_xml_path)]) }}" class="btn btn-sm btn-outline-secondary" title="XML">
                                        <i class="bi bi-filetype-xml"></i> XML
                                    </a>
                                @endif
                                @if($t->boleta_cdr_path)
                                    <a href="{{ route('sunat.download', ['type' => 'cdr', 'filename' => basename($t->boleta_cdr_path)]) }}" class="btn btn-sm btn-outline-info" title="CDR">
                                        <i class="bi bi-file-zip"></i> CDR
                                    </a>
                                @endif
                                @if(!$t->boleta_xml_path && !$t->boleta_cdr_path && !$t->boleta_ticket)
                                    <form method="POST" action="{{ route('boletas.enviar-individual', $t->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success" title="Reenviar a SUNAT (sendBill)">
                                            <i class="bi bi-send"></i> Enviar
                                        </button>
                                    </form>
                                @endif
                                @if($t->boleta_ticket && !$t->boleta_cdr_code && $t->boleta_estado_sunat !== 'ANULADO')
                                    <form method="POST" action="{{ route('boletas.reintentar', $t->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Reintentar" onclick="return confirm('Â¿Resetear boleta para reenviar?')">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reintentar
                                        </button>
                                    </form>
                                @endif
                                @if($t->boleta_estado_sunat === 'ACEPTADA' || $t->boleta_estado_sunat === 'ERROR_ANULACION')
                                    <form method="POST" action="{{ route('boletas.anular', $t->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular en SUNAT" onclick="return confirm('Â¿Anular esta boleta en SUNAT? Se enviarÃ¡ una comunicaciÃ³n de baja.')">
                                            <i class="bi bi-x-circle"></i> Anular
                                        </button>
                                    </form>
                                @endif
                                @if($t->boleta_estado_sunat === 'ANULADO' && !str_starts_with($t->boleta_ticket ?? '', 'NC:'))
                                    <form method="POST" action="{{ route('boletas.consultar-anulacion', $t->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Consultar estado de anulacion en SUNAT">
                                            <i class="bi bi-arrow-repeat"></i> Consultar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Aun no has emitido ninguna boleta.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="pagination-wrap pagination-compact">
    <div class="small text-muted">
        {{ $transacciones->total() }} boletas
    </div>
    <div>
        {{ $transacciones->onEachSide(0)->links() }}
    </div>
</div>

<form id="form-editar-dni" method="POST" class="d-none">
    @csrf
    <input type="hidden" name="dni" id="input-dni">
</form>

<script>
function editarDni(id, dniActual) {
    const dni = prompt('Ingrese DNI (8 digitos):', dniActual);
    if (dni && dni.length === 8) {
        const form = document.getElementById('form-editar-dni');
        form.action = '/boletas/' + id + '/editar-dni';
        document.getElementById('input-dni').value = dni;
        form.submit();
    } else if (dni) {
        alert('DNI debe tener 8 digitos.');
    }
}
</script>
@endsection
