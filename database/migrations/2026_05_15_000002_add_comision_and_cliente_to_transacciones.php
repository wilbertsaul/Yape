<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete()->after('synced_from_email');
            $table->decimal('comision_calculada', 12, 2)->nullable()->after('monto_transferencia');
            $table->decimal('comision', 12, 2)->nullable()->after('comision_calculada');
            $table->boolean('boleta_emitida')->default(false)->after('comision');
            $table->timestamp('boleta_emitida_at')->nullable()->after('boleta_emitida');
        });
    }

    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropColumn(['comision_calculada', 'comision', 'boleta_emitida', 'boleta_emitida_at']);
        });
    }
};
