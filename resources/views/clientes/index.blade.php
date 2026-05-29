@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
<div class="page-heading">
    <h4 class="text-primary">
        <i class="bi bi-person-lines-fill"></i> Clientes
    </h4>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nuevo Cliente</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('clientes.store') }}" class="row g-2 mobile-stack">
            @csrf
            <div class="col-12 col-md-5">
                <input type="text" class="form-control" name="nombre_completo" placeholder="Nombre completo" required>
            </div>
            <div class="col-6 col-md-3">
                <input type="text" class="form-control" name="dni" placeholder="DNI" maxlength="8" pattern="[0-9]{8}">
            </div>
            <div class="col-6 col-md-2">
                <input type="text" class="form-control" name="telefono" placeholder="Telefono" maxlength="20">
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-yape w-100">Agregar</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 table-card-list">
                <thead class="table-light">
                    <tr>
                        <th class="px-3">Nombre</th>
                        <th>DNI</th>
                        <th>Telefono</th>
                        <th>Transacciones</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes as $c)
                    <tr>
                        <td class="px-3 text-break-mobile">
                            <span class="cell-label">Nombre</span>
                            {{ $c->nombre_completo }}
                        </td>
                        <td>
                            <span class="cell-label">DNI</span>
                            @if($c->dni)
                                <span class="badge bg-success">{{ $c->dni }}</span>
                            @else
                                <span class="badge bg-warning text-dark">Pendiente</span>
                            @endif
                        </td>
                        <td>
                            <span class="cell-label">Telefono</span>
                            {{ $c->telefono ?? '-' }}
                        </td>
                        <td>
                            <span class="cell-label">Transacciones</span>
                            {{ $c->transacciones_count }}
                        </td>
                        <td>
                            <span class="cell-label">Acciones</span>
                            <div class="mobile-actions">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit-{{ $c->id }}">
                                    <i class="bi bi-pencil"></i> Editar
                                </button>
                                @if(Auth::user()->isAdmin())
                                <form action="{{ route('clientes.destroy', $c->id) }}" method="POST" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Eliminar?')">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr class="collapse" id="edit-{{ $c->id }}">
                        <td colspan="5" class="bg-light px-3 py-2">
                            <form method="POST" action="{{ route('clientes.update', $c->id) }}" class="row g-2 mobile-stack">
                                @csrf @method('PUT')
                                <div class="col-12 col-md-5">
                                    <input type="text" class="form-control" name="nombre_completo" value="{{ $c->nombre_completo }}" required>
                                </div>
                                <div class="col-6 col-md-3">
                                    <input type="text" class="form-control" name="dni" value="{{ $c->dni }}" maxlength="8" pattern="[0-9]{8}">
                                </div>
                                <div class="col-6 col-md-2">
                                    <input type="text" class="form-control" name="telefono" value="{{ $c->telefono }}" maxlength="20">
                                </div>
                                <div class="col-12 col-md-2">
                                    <button type="submit" class="btn btn-yape w-100">Guardar</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No hay clientes registrados. Se crearan automaticamente al sincronizar Yapes recibidos.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="pagination-wrap pagination-compact">
    <div class="small text-muted">
        {{ $clientes->total() }} clientes
    </div>
    <div>
        {{ $clientes->onEachSide(0)->links() }}
    </div>
</div>
@endsection
