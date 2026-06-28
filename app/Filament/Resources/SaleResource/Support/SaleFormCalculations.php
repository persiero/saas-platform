<?php

namespace App\Filament\Resources\SaleResource\Support;

use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\AfectacionIgv;
use Percy\Core\Services\Tenants\TenantPricingService;

class SaleFormCalculations
{
    public static function updateRow(Get $get, Set $set): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $afectacionId = $get('afectacion_igv_id') ?? 1;

        $rowData = app(TenantPricingService::class)
            ->rowData($quantity, $unitPrice, (int) $afectacionId);

        $set('unit_value', $rowData['unit_value']);
        $set('igv_amount', $rowData['igv_amount']);
        $set('total', $rowData['total']);
    }

    public static function updateTotals(Get $get, Set $set): void
    {
        // TRUCO AVANZADO: Detecta si estamos dentro del Repetidor o fuera de él.
        $items = $get('items');
        if ($items === null) {
            // Si es null, significa que estamos dentro de una fila. ¡Subimos un nivel!
            $items = $get('../../items') ?? [];
            $prefix = '../../'; // Usamos este prefijo para apuntar al panel derecho
        } else {
            // Si no es null, estamos en la raíz del formulario
            $prefix = '';
        }

        $op_gravadas = 0;
        $op_exoneradas = 0;
        $op_inafectas = 0;
        $igv = 0;
        $totalGeneral = 0;

        // 🌟 1. Obtenemos el IGV dinámico UNA SOLA VEZ antes del bucle
        // (Hacerlo afuera hace que el sistema sea mucho más rápido)
        $pricingService = app(TenantPricingService::class);

        foreach ($items as $item) {
            // Recalculamos al vuelo para tener la matemática 100% fresca
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $afecId = $item['afectacion_igv_id'] ?? 1;

            $afectacion = AfectacionIgv::find($afecId);

            // 🌟 2. Aplicamos el IGV dinámico
            $porcentaje = $pricingService->afectacionRate((int) $afecId);

            $rowTotal = $qty * $price;

            if ($afectacion && $afectacion->gravado) {
                $base = $rowTotal / (1 + $porcentaje);
                $op_gravadas += $base;
                $igv += ($rowTotal - $base);
            } elseif ($afectacion && str_starts_with($afectacion->codigo, '2')) {
                $op_exoneradas += $rowTotal;
            } elseif ($afectacion && str_starts_with($afectacion->codigo, '3')) {
                $op_inafectas += $rowTotal;
            }

            $totalGeneral += $rowTotal;
        }

        // Actualizamos el panel derecho inyectando los datos con su prefijo
        $set($prefix . 'op_gravadas', round($op_gravadas, 2));
        $set($prefix . 'op_exoneradas', round($op_exoneradas, 2));
        $set($prefix . 'op_inafectas', round($op_inafectas, 2));
        $set($prefix . 'igv', round($igv, 2));
        $set($prefix . 'total', round($totalGeneral, 2));
    }
}
