<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Sistema Yape' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Alpine.js para interactividad responsiva rápida -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full" x-data="{ mobileMenuOpen: false }">
    <div class="min-h-full flex flex-col">
        <!-- Navegación Responsiva -->
        <nav class="bg-indigo-700 lg:hidden sticky top-0 z-50 shadow-md">
            <div class="px-4 py-3 flex items-center justify-between">
                <span class="text-white font-bold text-lg tracking-tight">App Yape</span>
                <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="text-indigo-200 hover:text-white transition-colors focus:outline-none">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Menú Móvil -->
            <div x-show="mobileMenuOpen" x-transition class="border-t border-indigo-600 bg-indigo-800">
                <div class="px-2 pt-2 pb-3 space-y-1">
                    <a href="{{ route('users.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-white hover:bg-indigo-600">Usuarios</a>
                </div>
            </div>
        </nav>

        <!-- Sidebar para Escritorio -->
        <aside class="hidden lg:flex lg:flex-shrink-0 w-64 flex-col bg-indigo-900 min-h-screen fixed inset-y-0 shadow-xl">
            <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                <div class="flex items-center flex-shrink-0 px-4 mb-8 text-center">
                    <span class="text-white font-black text-2xl tracking-tighter">APP YAPE</span>
                </div>
                <nav class="flex-1 px-2 space-y-2">
                    <a href="{{ route('users.index') }}" class="text-indigo-100 hover:bg-indigo-800 group flex items-center px-3 py-3 text-sm font-semibold rounded-lg transition-all">
                        <svg class="mr-3 h-5 w-5 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        Usuarios
                    </a>
                    <!-- Agregar más ítems de menú aquí -->
                </nav>
            </div>
        </aside>

        <!-- Contenido Principal -->
        <div class="flex-1 lg:pl-64 flex flex-col min-w-0">
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <!-- Sistema de Alertas Dinámico -->
                @if (session('success'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 flex items-center justify-between shadow-sm">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                            <span class="text-sm font-medium text-green-800">{{ session('success') }}</span>
                        </div>
                        <button @click="show = false" class="text-green-500 hover:text-green-600 focus:outline-none">&times;</button>
                    </div>
                @endif

                @if (session('error'))
                    <div x-data="{ show: true }" x-show="show" class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 flex items-center justify-between shadow-sm">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
                            <span class="text-sm font-medium text-red-800">{{ session('error') }}</span>
                        </div>
                        <button @click="show = false" class="text-red-500 hover:text-red-600 focus:outline-none">&times;</button>
                    </div>
                @endif

                {{ $slot }}
            </main>

            <footer class="bg-white border-t border-gray-200 py-4 px-4 sm:px-6 lg:px-8">
                <p class="text-center text-sm text-gray-500">&copy; {{ date('Y') }} Sistema Yape - Control Financiero. v1.0</p>
            </footer>
        </div>
    </div>
</body>
</html>
