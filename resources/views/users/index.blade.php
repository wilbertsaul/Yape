@extends('layouts.app')

@section('title', 'Gestion de Usuarios')

@section('content')
<div class="page-heading">
    <h4 class="text-primary">
        <i class="bi bi-people"></i> Gestion de Usuarios
    </h4>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-person-plus"></i> Crear Nuevo Usuario</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('users.store') }}" class="row g-3">
            @csrf
            <div class="col-12 col-md-6">
                <label for="name" class="form-label">Nombre Completo</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="col-12 col-md-6">
                <label for="email" class="form-label">Correo Electronico</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="col-12 col-md-6">
                <label for="password" class="form-label">Contrasena</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="8">
            </div>
            <div class="col-12 col-md-6">
                <label for="password_confirmation" class="form-label">Confirmar Contrasena</label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
            </div>
            <div class="col-12 col-md-6">
                <label for="role" class="form-label">Rol</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="employee">Empleado</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-yape mobile-full">
                    <i class="bi bi-save"></i> Crear Usuario
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Lista de Usuarios</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 table-card-list">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td class="text-break-mobile">
                            <span class="cell-label">Nombre</span>
                            {{ $user->name }}
                        </td>
                        <td class="text-break-mobile">
                            <span class="cell-label">Email</span>
                            {{ $user->email }}
                        </td>
                        <td>
                            <span class="cell-label">Rol</span>
                            @if($user->role === 'admin')
                                <span class="badge bg-danger">Admin</span>
                            @else
                                <span class="badge bg-secondary">Empleado</span>
                            @endif
                        </td>
                        <td>
                            <span class="cell-label">Creado</span>
                            {{ $user->created_at->format('d/m/Y') }}
                        </td>
                        <td>
                            <span class="cell-label">Acciones</span>
                            <div class="mobile-actions">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal{{ $user->id }}">
                                    <i class="bi bi-pencil"></i> Editar
                                </button>
                                @if($user->id !== Auth::user()->id)
                                <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Eliminar usuario?')">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <div class="modal fade" id="editModal{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Editar Usuario</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" action="{{ route('users.update', $user->id) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre</label>
                                            <input type="text" class="form-control" name="name" value="{{ $user->name }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" value="{{ $user->email }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Nueva Contrasena (dejar en blanco)</label>
                                            <input type="password" class="form-control" name="password">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Rol</label>
                                            <select class="form-select" name="role" required>
                                                <option value="employee" {{ $user->role === 'employee' ? 'selected' : '' }}>Empleado</option>
                                                <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Administrador</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer mobile-stack">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-yape">Guardar Cambios</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No hay usuarios</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary mobile-full">
        <i class="bi bi-arrow-left"></i> Volver al Dashboard
    </a>
</div>
@endsection
