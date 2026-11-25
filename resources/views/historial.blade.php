@extends('layouts.app')

@section('title', 'Historial de Transacciones')

@section('content')
<div class="page-heading">
    <div>
        <h4 class="text-primary">
            <i class="bi bi-clock-history"></i> Historial de Transacciones
        </h4>
        <p class="text-muted mb-0">Listado detallado de todos los movimientos registrados en el sistema, incluyendo Yape y transferencias.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0 p-md-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 table-card-list">
                <thead class="table-light">
                    <tr>
                        <th class="px-3">Fecha</th>
                        <th>Tipo</th>
                        <th>Remitente / Op.</th>
                        <th>Monto</th>
                        <th>Comisión</th>
                        <th>Boleta</th>
                        <th>Fec. Emisión</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transacciones as $tx)
                    <tr>
                        <td class="px-3">
                            <span class="cell-label">Fecha</span>
                            <div class="fw-semibold">{{ $tx->fecha_hora->format('d/m/Y') }}</div>
                            <small class="text-muted">{{ $tx->fecha_hora->format('H:i') }}</small>
                        </td>
                        <td>
                            <span class="cell-label">Tipo</span>
                            @if($tx->tipo_movimiento == 'yape_enviado' || $tx->tipo_movimiento == 'registro_yape')
                                <span class="badge bg-yape-enviado">Yape Enviado</span>
                            @elseif($tx->tipo_movimiento == 'yape_recibido')
                                <span class="badge bg-success">Yape Recibido</span>
                            @elseif($tx->tipo_movimiento == 'traslado_fondos')
                                <span class="badge bg-warning text-dark">Traslado</span>
                            @elseif($tx->tipo_movimiento == 'transferencia_terceros')
                                <span class="badge bg-info text-dark">Transferencia</span>
                            @else
                                <span class="badge bg-secondary">{{ str_replace('_', ' ', ucfirst($tx->tipo_movimiento)) }}</span>
                            @endif
                        </td>
                        <td class="text-break-mobile">
                            <span class="cell-label">Remitente / Op.</span>
                            <div class="fw-semibold">{{ $tx->remitente_nombre ?? 'Sin nombre' }}</div>
                            <small class="text-muted">Op: {{ $tx->nro_operacion ?? '-' }}</small>
                        </td>
                        <td class="fw-bold">
                            <span class="cell-label">Monto</span>
                            S/ {{ number_format($tx->monto_transferencia, 2) }}
                        </td>
                        <td>
                            <span class="cell-label">Comisión</span>
                            @if($tx->comision)
                                S/ {{ number_format($tx->comision, 2) }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="cell-label">Boleta</span>
                            @if($tx->boleta_emitida)
                                <span class="badge bg-light text-dark">
                                    {{ $tx->boleta_serie }}-{{ $tx->boleta_numero }}
                                </span>
                            @else
                                <span class="badge bg-warning text-dark">Pendiente</span>
                            @endif
                        </td>
                        <td>
                            <span class="cell-label">Fec. Emisión</span>
                            @if($tx->boleta_emitida_at)
                                <span class="fw-semibold">{{ $tx->boleta_emitida_at->format('d/m/Y') }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No hay transacciones registradas.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="pagination-wrap pagination-compact">
    <div class="small text-muted">
        {{ $transacciones->total() }} transacciones
    </div>
    <div>
        {{ $transacciones->onEachSide(0)->links() }}
    </div>
</div>
@endsection
