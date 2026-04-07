@extends('layouts.app')

@section('title', 'Gestión de Saldos')

@section('content')
<div class="row">
    <div class="col-12 mb-3">
        <h4 class="text-primary">
            <i class="bi bi-cash-coin"></i> Gestión de Saldos Iniciales
        </h4>
    </div>
</div>

@if($ultimo)
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="card saldo-card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-wallet2"></i> Saldo Inicial Yape</h5>
            </div>
            <div class="card-body">
                <h2 class="text-primary mb-0">S/ {{ number_format($ultimo->saldo_yape, 2) }}</h2>
                <small class="text-muted">Última actualización: {{ $ultimo->fecha_actualizacion->format('d/m/Y') }}</small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card saldo-card shadow-sm">
            <div class="card-header" style="background-color: #10B981;">
                <h5 class="mb-0"><i class="bi bi-bank"></i> Saldo Inicial Secundaria</h5>
            </div>
            <div class="card-body">
                <h2 class="text-success mb-0">S/ {{ number_format($ultimo->saldo_secundario, 2) }}</h2>
                <small class="text-muted">Última actualización: {{ $ultimo->fecha_actualizacion->format('d/m/Y') }}</small>
            </div>
        </div>
    </div>
</div>
@endif

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Actualizar Saldos Iniciales</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('saldos.update') }}" class="row g-3">
            @csrf
            <div class="col-12 col-md-6">
                <label for="saldo_yape" class="form-label">Saldo Cuenta Yape (S/)</label>
                <input type="number" step="0.01" min="0" 
                       class="form-control input-mobile" 
                       id="saldo_yape" name="saldo_yape" 
                       value="{{ $ultimo ? $ultimo->saldo_yape : 0 }}"
                       required inputmode="decimal">
            </div>
            <div class="col-12 col-md-6">
                <label for="saldo_secundario" class="form-label">Saldo Cuenta Secundaria (S/)</label>
                <input type="number" step="0.01" min="0" 
                       class="form-control input-mobile" 
                       id="saldo_secundario" name="saldo_secundario" 
                       value="{{ $ultimo ? $ultimo->saldo_secundario : 0 }}"
                       required inputmode="decimal">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-yape">
                    <i class="bi bi-save"></i> Guardar Saldos
                </button>
            </div>
        </form>
    </div>
</div>

@if($saldos->count() > 0)
<div class="card shadow-sm mt-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historial de Cambios</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Saldo Yape</th>
                        <th>Saldo Secundaria</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($saldos as $saldo)
                    <tr>
                        <td>{{ $saldo->fecha_actualizacion->format('d/m/Y') }}</td>
                        <td>S/ {{ number_format($saldo->saldo_yape, 2) }}</td>
                        <td>S/ {{ number_format($saldo->saldo_secundario, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="mt-3">
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver al Dashboard
    </a>
</div>
@endsection