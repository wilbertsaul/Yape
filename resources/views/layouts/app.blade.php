<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'Sistema de Gestión Yape')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
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
        @media (max-width: 768px) {
            .container-fluid {
                padding: 10px;
            }
            .card {
                margin-bottom: 15px;
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

    <main class="container-fluid py-4">
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