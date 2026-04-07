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
        <div class="alert alert-success mt-2">
            <i class="bi bi-check-circle"></i> 
            <strong>Gmail conectado:</strong> Las transacciones se sincronizan cada 5 minutos.
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

<div class="row mt-3">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Registrar Operación Yape</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('transaccion.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="dni_cliente" class="form-label">DNI del Cliente</label>
                        <input type="text" class="form-control input-mobile" 
                               id="dni_cliente" name="dni_cliente" 
                               required maxlength="8" minlength="8" 
                               pattern="[0-9]{8}" 
                               inputmode="numeric"
                               placeholder="Ingrese 8 dígitos">
                        <div class="form-text">Ingrese el DNI de 8 dígitos</div>
                    </div>

                    <div class="mb-3">
                        <label for="monto_transferencia" class="form-label">Monto (S/)</label>
                        <input type="number" step="0.01" min="0.01" 
                               class="form-control input-mobile" 
                               id="monto_transferencia" name="monto_transferencia" 
                               required inputmode="decimal"
                               placeholder="0.00">
                    </div>

                    <button type="submit" class="btn btn-yape w-100 py-2">
                        <i class="bi bi-check-circle"></i> Confirmar Registro
                    </button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header" style="background-color: #F59E0B;">
                <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> Trasladar Fondos</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Trasladar desde Cuenta Secundaria a Cuenta Yape</p>
                <form method="POST" action="{{ route('traslado.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="monto_traslado" class="form-label">Monto a Trasladar (S/)</label>
                        <input type="number" step="0.01" min="0.01" 
                               class="form-control input-mobile" 
                               id="monto_traslado" name="monto_traslado" 
                               required inputmode="decimal"
                               placeholder="0.00">
                    </div>
                    <button type="submit" class="btn btn-warning w-100 py-2">
                        <i class="bi bi-arrow-left-right"></i> Trasladar Fondos
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Últimas Transacciones</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>DNI</th>
                                <th>Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transacciones as $t)
                            <tr>
                                <td>{{ $t->fecha_hora->format('d/m H:i') }}</td>
                                <td>
                                    @if($t->tipo_movimiento == 'registro_yape')
                                        <span class="badge bg-primary">Yape</span>
                                    @else
                                        <span class="badge bg-warning">Traslado</span>
                                    @endif
                                </td>
                                <td>{{ $t->dni_cliente }}</td>
                                <td class="{{ $t->tipo_movimiento == 'registro_yape' ? 'text-danger' : 'text-success' }}">
                                    {{ $t->tipo_movimiento == 'registro_yape' ? '-' : '+' }}S/ {{ number_format($t->monto_transferencia, 2) }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No hay transacciones</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('historial') }}" class="btn btn-sm btn-outline-primary">
                    Ver todo el historial <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection