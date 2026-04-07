<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldos_iniciales', function (Blueprint $table) {
            $table->id();
            $table->decimal('saldo_yape', 12, 2)->default(0);
            $table->decimal('saldo_secundario', 12, 2)->default(0);
            $table->date('fecha_actualizacion');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldos_iniciales');
    }
};