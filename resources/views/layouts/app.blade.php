<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sistema de Gestión Yape')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .app-shell {
            max-width: 1280px;
            margin: 0 auto;
        }
        .btn-yape {
            background-color: #6C2BD9;
            color: white;
        }
        .btn-yape:hover {
            background-color: #5a21b5;
            color: white;
        }
        .card-header {
            background-color: #6C2BD9;
            color: white;
        }
        .saldo-card {
            border-left: 4px solid #6C2BD9;
        }
        .input-mobile {
            font-size: 16px;
        }
        .bg-yape {
            background-color: #6C2BD9 !important;
            color: white !important;
        }
        .bg-yape-enviado {
            background-color: #572364 !important;
            color: white !important;
        }
        .page-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .page-heading h4 {
            margin-bottom: .25rem;
        }
        .responsive-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .table-card-list .cell-label {
            display: none;
        }
        .pagination-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem;
        }
        .pagination-compact .pagination {
            margin-bottom: 0;
            gap: 2px;
            flex-wrap: wrap;
            justify-content: flex-end;
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
        .text-break-mobile {
            overflow-wrap: anywhere;
        }
        @media (max-width: 768px) {
            .container-fluid {
                padding: 10px;
            }
            .card {
                margin-bottom: 15px;
            }
            .navbar-nav {
                gap: .25rem;
                padding-top: .75rem;
            }
            .navbar .nav-link,
            .navbar .btn.nav-link {
                border-radius: .5rem;
                padding: .65rem .75rem;
            }
            .navbar .nav-link:hover,
            .navbar .btn.nav-link:hover {
                background-color: rgba(255,255,255,.12);
            }
            .page-heading,
            .pagination-wrap {
                align-items: stretch;
                flex-direction: column;
            }
            .responsive-actions > .btn,
            .responsive-actions > form,
            .responsive-actions > form .btn {
                width: 100%;
            }
            .mobile-full {
                width: 100%;
            }
            .table-card-list {
                border-collapse: separate;
                border-spacing: 0 .75rem;
            }
            .table-card-list thead {
                display: none;
            }
            .table-card-list tbody,
            .table-card-list tr,
            .table-card-list td {
                display: block;
                width: 100%;
            }
            .table-card-list tr {
                background: #fff;
                border: 1px solid #e9ecef;
                border-radius: .5rem;
                box-shadow: 0 .125rem .25rem rgba(0,0,0,.04);
                overflow: hidden;
            }
            .table-card-list tr.collapse:not(.show) {
                display: none;
            }
            .table-card-list td {
                border: 0;
                padding: .55rem .85rem;
            }
            .table-card-list td:first-child {
                padding-top: .85rem;
            }
            .table-card-list td:last-child {
                padding-bottom: .85rem;
            }
            .table-card-list .cell-label {
                color: #6c757d;
                display: block;
                font-size: .72rem;
                font-weight: 700;
                letter-spacing: .02em;
                text-transform: uppercase;
                margin-bottom: .15rem;
            }
            .table-card-list .mobile-actions {
                display: flex;
                flex-wrap: wrap;
                gap: .5rem;
            }
            .table-card-list .mobile-actions > .btn,
            .table-card-list .mobile-actions > form,
            .table-card-list .mobile-actions > form .btn {
                flex: 1 1 8rem;
            }
            .mobile-stack {
                flex-direction: column !important;
                align-items: stretch !important;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #6C2BD9;">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <i class="bi bi-wallet2"></i> Yape Pagos
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard') }}">
                            <i class="bi bi-house"></i> Inicio
                        </a>
                    </li>
                    @auth
                        @if(Auth::user()->isAdmin())
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('users.index') }}">
                                <i class="bi bi-people"></i> Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('saldos.index') }}">
                                <i class="bi bi-cash-coin"></i> Saldos
                            </a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('historial') }}">
                                <i class="bi bi-clock-history"></i> Historial
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('boletas.pendientes') }}">
                                <i class="bi bi-receipt"></i> Boletas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('clientes.index') }}">
                                <i class="bi bi-person-lines-fill"></i> Clientes
                            </a>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-link nav-link">
                                    <i class="bi bi-box-arrow-right"></i> Salir
                                </button>
                            </form>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main class="container-fluid app-shell py-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @yield('scripts')
</body>
</html>
