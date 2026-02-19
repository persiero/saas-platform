<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Percy\Core\Models\AfectacionIgv;
use Percy\Core\Models\UnidadSunat;

class SunatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sembrando Afectaciones (Los más comunes)
        AfectacionIgv::insert([
            ['codigo' => '10', 'descripcion' => 'Gravado - Operación Onerosa', 'gravado' => true, 'porcentaje' => 18.00],
            ['codigo' => '20', 'descripcion' => 'Exonerado - Operación Onerosa', 'gravado' => false, 'porcentaje' => 0.00],
            ['codigo' => '21', 'descripcion' => 'Exonerado - Transferencia Gratuita', 'gravado' => false, 'porcentaje' => 0.00],
            ['codigo' => '30', 'descripcion' => 'Inafecto - Operación Onerosa', 'gravado' => false, 'porcentaje' => 0.00],
        ]);

        // Sembrando Unidades (Las más comunes)
        UnidadSunat::insert([
            ['codigo' => 'NIU', 'descripcion' => 'Unidad (Bienes)'],
            ['codigo' => 'ZZ',  'descripcion' => 'Servicios'],
            ['codigo' => 'KGM', 'descripcion' => 'Kilogramos'],
            ['codigo' => 'LTR', 'descripcion' => 'Litros'],
        ]);
    }
}
