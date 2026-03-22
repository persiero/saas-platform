<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Percy\Core\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TopSoldProductsChart extends ChartWidget
{
    protected static ?string $heading = 'Top 5 Productos Más Vendidos (Este Mes)';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // 🌟 CAMBIO CLAVE: Hacemos Join con la tabla products para obtener el nombre ACTUAL
        $topProducts = SaleItem::join('products', 'sale_items.product_id', '=', 'products.id')
            ->select(
                'products.name as actual_name', // Jalamos el nombre del catálogo
                DB::raw('SUM(sale_items.quantity) as total_quantity')
            )
            ->whereHas('sale', function ($query) use ($tenantId, $startOfMonth, $endOfMonth) {
                $query->where('tenant_id', $tenantId)
                    ->whereBetween('sold_at', [$startOfMonth, $endOfMonth])
                    ->where('sunat_status', '!=', 'rejected');
            })
            ->groupBy('products.id', 'products.name') // Agrupamos por el ID real del producto
            ->orderBy('total_quantity', 'desc')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Unidades Vendidas',
                    'data' => $topProducts->pluck('total_quantity')->toArray(),
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#047857',
                    'borderRadius' => 6,
                ],
            ],
            // 🌟 CAMBIO CLAVE: Usamos el alias 'actual_name' para las etiquetas
            'labels' => $topProducts->pluck('actual_name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
