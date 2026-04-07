<?php

use App\Http\Controllers\TransaccionController;
use App\Http\Controllers\SaldoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GmailAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/gmail/redirect', [GmailAuthController::class, 'redirect'])->name('gmail.redirect');
Route::get('/gmail/callback', [GmailAuthController::class, 'callback']);

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [TransaccionController::class, 'index'])->name('dashboard');
    
    Route::post('/transaccion', [TransaccionController::class, 'store'])->name('transaccion.store');
    Route::post('/traslado', [TransaccionController::class, 'traslado'])->name('traslado.store');
    
    Route::get('/historial', [TransaccionController::class, 'historial'])->name('historial');
    Route::delete('/transaccion/{id}', [TransaccionController::class, 'destroy'])->name('transaccion.destroy');
    
    Route::get('/saldos', [SaldoController::class, 'index'])->name('saldos.index');
    Route::put('/saldos', [SaldoController::class, 'update'])->name('saldos.update');
    
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
});