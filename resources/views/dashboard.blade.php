@extends('layouts.app')

@section('title', 'Dashboard - Sistema Yape')

@section('content')
<div class="row">
    <div class="col-12 mb-3">
        <h4 class="text-primary">
            <i class="bi bi-person-circle"></i> Bienvenido, {{ Auth::user()->name }}
            <small class="text-muted">({{ Auth::user()->role }})</small>
        </h4>
        
        @if(!file_exists(storage_path('app/gmail_token.json')))
        <div class="alert alert-info mt-2">
            <i class="bi bi-envelope"></i> 
            <strong>Autorización Gmail requerida:</strong> 
            <a href="/gmail/redirect" class="btn btn-sm btn-primary ms-2">
                <i class="bi bi-google"></i> Autorizar Gmail
            </a>
            <p class="mb-0 small text-muted">Necesitas autorizar para sincronizar transacciones automáticamente desde tu correo.</p>
        </div>
        @else
        <div class="alert alert-success mt-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <i class="bi bi-check-circle"></i> 
                <strong>Gmail conectado:</strong> Las transacciones se sincronizan cada 5 minutos.
            </div>
            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('sync.manual') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-arrow-repeat"></i> Sincronizar Ahora
                    </button>
                </form>
                <form method="POST" action="{{ route('sync.manual') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="force" value="1">
                    <button type="submit" class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-lightning"></i> Forzar
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="card saldo-card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-wallet2"></i> Saldo Cuenta Yape</h5>
            </div>
            <div class="card-body">
                <h2 class="text-primary mb-0">S/ {{ number_format($saldos['saldo_yape'], 2) }}</h2>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card saldo-card shadow-sm">
            <div class="card-header" style="background-color: #10B981;">
                <h5 class="mb-0"><i class="bi bi-bank"></i> Saldo Cuenta Secundaria</h5>
            </div>
            <div class="card-body">
                <h2 class="text-success mb-0">S/ {{ number_format($saldos['saldo_secundario'], 2) }}</h2>
            </div>
        </div>
    </div>
</div>

@if($boletasPendientes > 0)
<div class="row mt-3">
    <div class="col-12">
        <div class="alert alert-warning d-flex align-items-center justify-content-between gap-2 mobile-stack">
            <div>
                <i class="bi bi-receipt"></i>
                <strong>{{ $boletasPendientes }} boleta(s) pendiente(s) por emitir</strong>
                — Registra los DNIs faltantes y marca como emitidas.
            </div>
            <a href="{{ route('boletas.pendientes') }}" class="btn btn-sm btn-warning">
                Ir a Boletas <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
</div>
@endif

<div class="row mt-3 g-3">
    <div class="col-12 col-md-4">
        <div class="card shadow-sm border-0 bg-primary bg-opacity-10">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Comisiones Totales</h6>
                <h3 class="text-primary mb-0">S/ {{ number_format($totalComisiones, 2) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm border-0 bg-warning bg-opacity-10">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Pendientes de Emitir</h6>
                <h3 class="text-warning mb-0">S/ {{ number_format($comisionesPendientes, 2) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm border-0 bg-success bg-opacity-10">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Emitidas</h6>
                <h3 class="text-success mb-0">S/ {{ number_format($comisionesEmitidas, 2) }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Últimas Transacciones</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-card-list">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Cliente</th>
                                <th>DNI</th>
                                <th>Monto</th>
                                <th>Comisión</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transacciones as $t)
                            <tr>
                                <td>
                                    <span class="cell-label">Fecha</span>
                                    {{ $t->fecha_hora->format('d/m H:i') }}
                                </td>
                                <td>
                                    <span class="cell-label">Tipo</span>
                                    @if($t->tipo_movimiento == 'yape_enviado' || $t->tipo_movimiento == 'registro_yape')
                                        <span class="badge bg-yape-enviado">Yape Enviado</span>
                                    @elseif($t->tipo_movimiento == 'yape_recibido')
                                        <span class="badge bg-success">Yape Recibido</span>
                                    @elseif($t->tipo_movimiento == 'traslado_fondos')
                                        <span class="badge bg-warning">Traslado</span>
                                    @elseif($t->tipo_movimiento == 'transferencia_terceros')
                                        <span class="badge bg-info">Transferencia</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $t->tipo_movimiento }}</span>
                                    @endif
                                </td>
                                <td class="text-break-mobile">
                                    <span class="cell-label">Cliente</span>
                                    {{ $t->remitente_nombre ?? '-' }}
                                </td>
                                <td>
                                    <span class="cell-label">DNI</span>
                                    {{ $t->dni_cliente }}
                                </td>
                                <td class="{{ in_array($t->tipo_movimiento, ['yape_enviado', 'registro_yape', 'transferencia_terceros']) ? 'text-danger' : 'text-success' }}">
                                    <span class="cell-label">Monto</span>
                                    {{ in_array($t->tipo_movimiento, ['yape_enviado', 'registro_yape', 'transferencia_terceros']) ? '-' : '+' }}S/ {{ number_format($t->monto_transferencia, 2) }}
                                </td>
                                <td>
                                    <span class="cell-label">ComisiÃ³n</span>
                                    @if($t->comision !== null)
                                        S/ {{ number_format($t->comision, 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No hay transacciones</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('historial') }}" class="btn btn-sm btn-outline-primary mobile-full">
                    Ver todo el historial <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
