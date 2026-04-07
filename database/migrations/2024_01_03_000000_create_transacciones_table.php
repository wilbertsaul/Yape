<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('dni_cliente', 8);
            $table->decimal('monto_transferencia', 12, 2);
            $table->decimal('saldo_yape_momento', 12, 2);
            $table->decimal('saldo_secundaria_momento', 12, 2);
            $table->enum('tipo_movimiento', ['registro_yape', 'traslado_fondos']);
            $table->timestamp('fecha_hora');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transacciones');
    }
};