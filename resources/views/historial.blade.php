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
                        <th>Cliente</th>
                        <th>DNI Cliente</th>
                        <th>Monto</th>
                        <th>Comisión</th>
                        <th>Boleta</th>
                        <th>Fec. Emisión</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transacciones as $t)
                    <tr>
                        <td class="px-3">{{ $t->fecha_hora->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $t->user->name }}</td>
                        <td>
                            @if($t->tipo_movimiento == 'yape_enviado' || $t->tipo_movimiento == 'registro_yape')
                                <span class="badge bg-yape-enviado">Yape Enviado</span>
                            @elseif($t->tipo_movimiento == 'yape_recibido')
                                <span class="badge bg-success">Yape Recibido</span>
                                    @elseif($t->tipo_movimiento == 'traslado_fondos')
                                        <span class="badge bg-warning text-dark">Traslado Fondos</span>
                                    @elseif($t->tipo_movimiento == 'transferencia_terceros')
                                        <span class="badge bg-info">Transferencia</span>
                                    @else
                                <span class="badge bg-secondary">{{ $t->tipo_movimiento }}</span>
                            @endif
                        </td>
                        <td>{{ $t->remitente_nombre ?? '-' }}</td>
                        <td>{{ $t->dni_cliente }}</td>
                        <td class="{{ in_array($t->tipo_movimiento, ['yape_enviado', 'registro_yape', 'transferencia_terceros']) ? 'text-danger' : 'text-success' }}">
                            {{ in_array($t->tipo_movimiento, ['yape_enviado', 'registro_yape', 'transferencia_terceros']) ? '-' : '+' }}S/ {{ number_format($t->monto_transferencia, 2) }}
                        </td>
                        <td>
                            @if($t->comision !== null)
                                S/ {{ number_format($t->comision, 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($t->boleta_emitida && $t->boleta_serie && $t->boleta_numero)
                                <span class="badge bg-primary">{{ $t->boleta_serie }}-{{ str_pad($t->boleta_numero, 4, '0', STR_PAD_LEFT) }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($t->boleta_emitida_at)
                                {{ $t->boleta_emitida_at->format('d/m/Y H:i') }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
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