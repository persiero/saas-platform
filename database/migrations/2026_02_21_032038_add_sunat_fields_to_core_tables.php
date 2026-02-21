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
        // 1. Adaptar Clientes (Catálogo 06)
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'document_type')) {
                $table->string('document_type', 1)->default('1')->after('name')->comment('1=DNI, 6=RUC, -=Sin Doc');
            }
            if (!Schema::hasColumn('customers', 'document_number')) {
                $table->string('document_number', 15)->nullable()->after('document_type');
            }
            if (!Schema::hasColumn('customers', 'address')) {
                $table->string('address', 255)->nullable()->after('document_number');
            }
        });

        // 2. Adaptar Productos (Catálogo 03)
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'unit_code')) {
                $table->string('unit_code', 3)->default('NIU')->after('name')->comment('NIU=Unidad, ZZ=Servicio');
            }
        });

        // 3. Adaptar Ventas
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'currency')) {
                $table->string('currency', 3)->default('PEN')->after('correlative')->comment('PEN=Soles, USD=Dólares');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
        
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unit_code');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'document_number', 'address']);
        });
    }
};
