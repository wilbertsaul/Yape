<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->text('boleta_nc_xml_path')->nullable()->after('boleta_cdr_path');
            $table->text('boleta_nc_cdr_path')->nullable()->after('boleta_nc_xml_path');
        });
    }

    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropColumn(['boleta_nc_xml_path', 'boleta_nc_cdr_path']);
        });
    }
};
