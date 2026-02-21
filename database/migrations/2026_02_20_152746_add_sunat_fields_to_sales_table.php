<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Agregamos los 3 campos clave para SUNAT
            $table->string('document_type', 2)->default('03')->after('customer_id')->comment('01=Factura, 03=Boleta');
            $table->string('series', 4)->nullable()->after('document_type');
            $table->integer('correlative')->nullable()->after('series');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'series', 'correlative']);
        });
    }
};
