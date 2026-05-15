<?php

use App\Http\Controllers\TransaccionController;
use App\Http\Controllers\SaldoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GmailAuthController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\BoletaController;
use App\Http\Controllers\ClienteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/gmail/redirect', [GmailAuthController::class, 'redirect'])->name('gmail.redirect');
Route::get('/gmail/callback', [GmailAuthController::class, 'callback']);

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [TransaccionController::class, 'index'])->name('dashboard');
    Route::post('/sync/manual', [SyncController::class, 'manual'])->name('sync.manual');

    Route::get('/historial', [TransaccionController::class, 'historial'])->name('historial');
    Route::delete('/transaccion/{id}', [TransaccionController::class, 'destroy'])->name('transaccion.destroy');
    
    Route::get('/saldos', [SaldoController::class, 'index'])->name('saldos.index');
    Route::put('/saldos', [SaldoController::class, 'update'])->name('saldos.update');
    
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::put('/clientes/{id}', [ClienteController::class, 'update'])->name('clientes.update');
    Route::delete('/clientes/{id}', [ClienteController::class, 'destroy'])->name('clientes.destroy');

    Route::get('/boletas/pendientes', [BoletaController::class, 'pendientes'])->name('boletas.pendientes');
    Route::get('/boletas/emitidas', [BoletaController::class, 'emitidas'])->name('boletas.emitidas');
    Route::post('/boletas/{id}/marcar-emitida', [BoletaController::class, 'marcarEmitida'])->name('boletas.marcar-emitida');
    Route::post('/boletas/{id}/actualizar-comision', [BoletaController::class, 'actualizarComision'])->name('boletas.actualizar-comision');
});