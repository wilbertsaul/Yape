<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrelativoBoleta extends Model
{
    protected $table = 'correlativos_boletas';

    protected $fillable = [
        'serie',
        'ultimo_numero',
    ];
}
