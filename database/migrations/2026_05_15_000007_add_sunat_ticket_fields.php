<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->string('boleta_ticket', 40)->nullable()->after('boleta_hash');
            $table->string('boleta_xml_path', 255)->nullable()->after('boleta_ticket');
            $table->string('boleta_cdr_path', 255)->nullable()->after('boleta_xml_path');
            $table->string('boleta_cdr_code', 10)->nullable()->after('boleta_cdr_path');
            $table->text('boleta_cdr_notes')->nullable()->after('boleta_cdr_code');
            $table->timestamp('boleta_enviada_at')->nullable()->after('boleta_cdr_notes');
        });
    }

    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropColumn([
                'boleta_ticket', 'boleta_xml_path', 'boleta_cdr_path',
                'boleta_cdr_code', 'boleta_cdr_notes', 'boleta_enviada_at',
            ]);
        });
    }
};