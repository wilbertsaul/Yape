<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->string('email_message_id')->nullable()->unique()->after('user_id');
            $table->string('remitente_nombre')->nullable()->after('dni_cliente');
            $table->string('remitente_telefono')->nullable()->after('remitente_nombre');
            $table->string('nro_operacion')->nullable()->unique()->after('remitente_telefono');
            $table->string('tipo_operacion')->nullable()->after('nro_operacion');
            $table->string('canal')->nullable()->after('tipo_operacion');
            $table->string('origen_cuenta')->nullable()->after('canal');
            $table->string('destino')->nullable()->after('origen_cuenta');
            $table->text('mensaje')->nullable()->after('destino');
            $table->boolean('synced_from_email')->default(false)->after('mensaje');
            $table->timestamp('synced_at')->nullable()->after('synced_from_email');
        });
    }

    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};