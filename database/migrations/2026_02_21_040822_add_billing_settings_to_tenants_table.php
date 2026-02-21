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
        Schema::table('tenants', function (Blueprint $table) {
            // 1. Datos de la Empresa
            if (!Schema::hasColumn('tenants', 'ruc')) {
                $table->string('ruc', 11)->nullable()->after('name');
                $table->string('business_name')->nullable()->after('ruc')->comment('Razón Social');
                $table->string('address')->nullable()->after('business_name');
                $table->string('ubigeo', 6)->nullable()->after('address');
                $table->string('phone')->nullable()->after('ubigeo');
                $table->string('email')->nullable()->after('phone');
            }

            // 2. Credenciales SUNAT
            if (!Schema::hasColumn('tenants', 'sunat_environment')) {
                $table->string('sunat_environment')->default('beta')->comment('beta o production');
                $table->string('sunat_sol_user')->nullable();
                $table->string('sunat_sol_pass')->nullable();
                $table->string('sunat_certificate')->nullable()->comment('Ruta del archivo PEM/PFX');
                $table->string('sunat_certificate_password')->nullable();
            }

            // 3. Configuración del Negocio
            if (!Schema::hasColumn('tenants', 'igv_percentage')) {
                $table->decimal('igv_percentage', 5, 2)->default(18.00);
                $table->boolean('prices_include_igv')->default(true);
                $table->boolean('auto_send_sunat')->default(false)->comment('Envío automático en segundo plano');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            //
        });
    }
};
