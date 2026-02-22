<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Actualización de la tabla de Ventas
        Schema::table('sales', function (Blueprint $table) {
            // Estado y Respuesta de SUNAT
            $table->string('sunat_status')->default('pending')->after('status');
            $table->string('sunat_code')->nullable()->after('sunat_status');
            $table->text('sunat_description')->nullable()->after('sunat_code');
            $table->string('sunat_hash')->nullable()->after('sunat_description');

            // Rutas de archivos y Leyendas
            $table->text('legend_text')->nullable()->after('sunat_hash');
            $table->string('sunat_xml_path')->nullable()->after('legend_text');
            $table->string('sunat_cdr_path')->nullable()->after('sunat_xml_path');
            $table->string('sunat_pdf_path')->nullable()->after('sunat_cdr_path');

            // Información de Pago y Notas de Crédito
            $table->string('payment_method')->default('Contado')->after('sunat_pdf_path');
            $table->string('affected_document_type')->nullable()->after('payment_method');
            $table->string('affected_document_series')->nullable()->after('affected_document_type');
            $table->string('affected_document_correlative')->nullable()->after('affected_document_series');
            $table->string('credit_note_type')->nullable()->after('affected_document_correlative');
        });

        // 2. Actualización de la tabla de Ítems de Venta
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('unit_code')->default('NIU')->after('item_name');
            $table->decimal('igv_percentage', 5, 2)->default(18.00)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'sunat_status', 'sunat_code', 'sunat_description', 'sunat_hash',
                'legend_text', 'sunat_xml_path', 'sunat_cdr_path', 'sunat_pdf_path',
                'payment_method', 'affected_document_type', 'affected_document_series',
                'affected_document_correlative', 'credit_note_type'
            ]);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['unit_code', 'igv_percentage']);
        });
    }
};
