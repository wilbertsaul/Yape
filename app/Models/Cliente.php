<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'nombre_completo',
        'dni',
        'telefono',
        'notas',
    ];

    public function transacciones()
    {
        return $this->hasMany(Transaccion::class, 'cliente_id');
    }
}
