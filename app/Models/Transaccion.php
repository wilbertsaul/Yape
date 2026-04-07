<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaccion extends Model
{
    use HasFactory;

    protected $table = 'transacciones';

    protected $fillable = [
        'user_id',
        'dni_cliente',
        'monto_transferencia',
        'saldo_yape_momento',
        'saldo_secundaria_momento',
        'tipo_movimiento',
        'fecha_hora',
        'email_message_id',
        'remitente_nombre',
        'remitente_telefono',
        'nro_operacion',
        'tipo_operacion',
        'canal',
        'origen_cuenta',
        'destino',
        'mensaje',
        'synced_from_email',
        'synced_at',
    ];

    protected $casts = [
        'monto_transferencia' => 'decimal:2',
        'saldo_yape_momento' => 'decimal:2',
        'saldo_secundaria_momento' => 'decimal:2',
        'fecha_hora' => 'datetime',
        'synced_from_email' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function registrarYape($userId, $dniCliente, $monto, $saldoYape, $saldoSecundaria)
    {
        return static::create([
            'user_id' => $userId,
            'dni_cliente' => $dniCliente,
            'monto_transferencia' => $monto,
            'saldo_yape_momento' => $saldoYape,
            'saldo_secundaria_momento' => $saldoSecundaria,
            'tipo_movimiento' => 'registro_yape',
            'fecha_hora' => now(),
        ]);
    }

    public static function registrarTraslado($userId, $monto, $saldoYape, $saldoSecundaria)
    {
        return static::create([
            'user_id' => $userId,
            'dni_cliente' => 'TRASLADO',
            'monto_transferencia' => $monto,
            'saldo_yape_momento' => $saldoYape,
            'saldo_secundaria_momento' => $saldoSecundaria,
            'tipo_movimiento' => 'traslado_fondos',
            'fecha_hora' => now(),
        ]);
    }

    public static function registrarDesdeEmail($data)
    {
        return static::create([
            'user_id' => $data['user_id'] ?? 1,
            'dni_cliente' => substr($data['remitente_telefono'] ?? 'EMAIL', 0, 20),
            'monto_transferencia' => $data['monto'],
            'saldo_yape_momento' => 0,
            'saldo_secundaria_momento' => 0,
            'tipo_movimiento' => 'registro_yape',
            'fecha_hora' => $data['fecha_hora'] ?? now(),
            'email_message_id' => substr($data['email_message_id'] ?? '', 0, 255),
            'remitente_nombre' => substr($data['remitente_nombre'] ?? '', 0, 100),
            'remitente_telefono' => substr($data['remitente_telefono'] ?? '', 0, 20),
            'nro_operacion' => substr($data['numero_operacion'] ?? '', 0, 50),
            'tipo_operacion' => substr($data['tipo_operacion'] ?? '', 0, 50),
            'canal' => substr($data['canal'] ?? '', 0, 50),
            'origen_cuenta' => substr($data['origen_cuenta'] ?? '', 0, 50),
            'destino' => substr($data['destino'] ?? '', 0, 50),
            'mensaje' => substr($data['mensaje'] ?? '', 0, 255),
            'synced_from_email' => true,
            'synced_at' => now(),
        ]);
    }
}