@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
<div class="row">
    <div class="col-12 mb-3">
        <h4 class="text-primary">
            <i class="bi bi-person-lines-fill"></i> Clientes
        </h4>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nuevo Cliente</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('clientes.store') }}" class="row g-2">
            @csrf
            <div class="col-12 col-md-5">
                <input type="text" class="form-control" name="nombre_completo" placeholder="Nombre completo" required>
            </div>
            <div class="col-6 col-md-3">
                <input type="text" class="form-control" name="dni" placeholder="DNI" maxlength="8" pattern="[0-9]{8}">
            </div>
            <div class="col-6 col-md-2">
                <input type="text" class="form-control" name="telefono" placeholder="Teléfono" maxlength="20">
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
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-3">Nombre</th>
                        <th>DNI</th>
                        <th>Teléfono</th>
                        <th>Transacciones</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes as $c)
                    <tr>
                        <td class="px-3">{{ $c->nombre_completo }}</td>
                        <td>
                            @if($c->dni)
                                <span class="badge bg-success">{{ $c->dni }}</span>
                            @else
                                <span class="badge bg-warning text-dark">Pendiente</span>
                            @endif
                        </td>
                        <td>{{ $c->telefono ?? '-' }}</td>
                        <td>{{ $c->transacciones_count }}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit-{{ $c->id }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            @if(Auth::user()->isAdmin())
                            <form action="{{ route('clientes.destroy', $c->id) }}" method="POST" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    <tr class="collapse" id="edit-{{ $c->id }}">
                        <td colspan="5" class="bg-light px-3 py-2">
                            <form method="POST" action="{{ route('clientes.update', $c->id) }}" class="row g-2">
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
                        <td colspan="5" class="text-center text-muted py-4">No hay clientes registrados. Se crearán automáticamente al sincronizar Yapes recibidos.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $clientes->links() }}
</div>
@endsection
