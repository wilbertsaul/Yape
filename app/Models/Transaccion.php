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
        'comision_calculada',
        'comision',
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
        'cliente_id',
        'boleta_emitida',
        'boleta_emitida_at',
    ];

    protected $casts = [
        'monto_transferencia' => 'decimal:2',
        'comision_calculada' => 'decimal:2',
        'comision' => 'decimal:2',
        'saldo_yape_momento' => 'decimal:2',
        'saldo_secundaria_momento' => 'decimal:2',
        'fecha_hora' => 'datetime',
        'synced_from_email' => 'boolean',
        'synced_at' => 'datetime',
        'boleta_emitida' => 'boolean',
        'boleta_emitida_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function calcularComision(float $monto): float
    {
        if ($monto <= 500) return 1;
        if ($monto <= 2000) return 2;
        if ($monto <= 5000) return 5;
        return 10;
    }

    public static function buscarOcrearCliente(string $nombre): ?Cliente
    {
        $nombre = trim($nombre);
        if (empty($nombre)) return null;

        $cliente = Cliente::whereRaw('TRIM(nombre_completo) = ?', [$nombre])->first();
        if ($cliente) return $cliente;

        return Cliente::create(['nombre_completo' => $nombre]);
    }

    public function asignarClienteYComision(): void
    {
        if (in_array($this->tipo_movimiento, ['yape_recibido', 'yape_enviado', 'transferencia_terceros'])) {
            $this->comision_calculada = static::calcularComision((float) $this->monto_transferencia);
            if ($this->comision === null) {
                $this->comision = $this->comision_calculada;
            }
            if ($this->tipo_movimiento === 'yape_recibido' && $this->remitente_nombre) {
                $cliente = static::buscarOcrearCliente($this->remitente_nombre);
                if ($cliente) {
                    $this->cliente_id = $cliente->id;
                }
            }
            $this->save();
        }
    }

    public static function registrarDesdeEmail($data)
    {
        return static::create([
            'user_id' => $data['user_id'] ?? 1,
            'dni_cliente' => substr($data['remitente_telefono'] ?? 'EMAIL', 0, 20),
            'monto_transferencia' => $data['monto'],
            'saldo_yape_momento' => 0,
            'saldo_secundaria_momento' => 0,
            'tipo_movimiento' => $data['tipo_movimiento'] ?? 'yape_enviado',
            'fecha_hora' => $data['fecha_hora'] ?? now(),
            'email_message_id' => substr($data['email_message_id'] ?? '', 0, 255),
            'remitente_nombre' => substr($data['remitente_nombre'] ?? '', 0, 100),
            'remitente_telefono' => substr($data['remitente_telefono'] ?? '', 0, 20),
            'nro_operacion' => !empty($data['numero_operacion']) ? substr($data['numero_operacion'], 0, 50) : null,
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