<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->string('boleta_serie', 10)->nullable()->after('boleta_emitida_at');
            $table->integer('boleta_numero')->nullable()->after('boleta_serie');
            $table->text('boleta_xml_url')->nullable()->after('boleta_numero');
            $table->text('boleta_pdf_url')->nullable()->after('boleta_xml_url');
            $table->text('boleta_cdr_url')->nullable()->after('boleta_pdf_url');
            $table->string('boleta_estado_sunat', 30)->nullable()->after('boleta_cdr_url');
            $table->string('boleta_hash', 255)->nullable()->after('boleta_estado_sunat');
            $table->json('boleta_respuesta_raw')->nullable()->after('boleta_hash');
        });

        Schema::create('correlativos_boletas', function (Blueprint $table) {
            $table->id();
            $table->string('serie', 10)->unique();
            $table->integer('ultimo_numero')->default(0);
            $table->timestamps();
        });

        DB::table('correlativos_boletas')->insert([
            'serie' => config('api-sunat.serie', 'EB01'),
            'ultimo_numero' => config('api-sunat.ultimo_correlativo', 8586),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropColumn([
                'boleta_serie', 'boleta_numero', 'boleta_xml_url', 'boleta_pdf_url',
                'boleta_cdr_url', 'boleta_estado_sunat', 'boleta_hash', 'boleta_respuesta_raw',
            ]);
        });

        Schema::dropIfExists('correlativos_boletas');
    }
};
