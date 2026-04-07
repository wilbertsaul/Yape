<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaldoInicial extends Model
{
    use HasFactory;

    protected $table = 'saldos_iniciales';

    protected $fillable = [
        'saldo_yape',
        'saldo_secundario',
        'fecha_actualizacion',
    ];

    protected $casts = [
        'saldo_yape' => 'decimal:2',
        'saldo_secundario' => 'decimal:2',
        'fecha_actualizacion' => 'date',
    ];

    public static function obtenerSaldos()
    {
        return static::orderBy('fecha_actualizacion', 'desc')->first();
    }

    public static function actualizarSaldos($saldoYape, $saldoSecundario)
    {
        return static::create([
            'saldo_yape' => $saldoYape,
            'saldo_secundario' => $saldoSecundario,
            'fecha_actualizacion' => now()->toDateString(),
        ]);
    }
}