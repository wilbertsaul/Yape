<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transacciones MODIFY COLUMN tipo_movimiento ENUM('registro_yape','traslado_fondos','yape_recibido','yape_enviado') NOT NULL DEFAULT 'registro_yape'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transacciones MODIFY COLUMN tipo_movimiento ENUM('registro_yape','traslado_fondos') NOT NULL DEFAULT 'registro_yape'");
    }
};
