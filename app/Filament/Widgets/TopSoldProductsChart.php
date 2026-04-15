<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Percy\Core\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TopSoldProductsChart extends ChartWidget
{
    protected static ?string $heading = 'Top 5 Conceptos Más Vendidos (Este Mes)';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // 🌟 MAGIA SQL: Usamos LEFT JOIN y un CASE para limpiar los nombres antes de agruparlos
        $topProducts = SaleItem::leftJoin('products', 'sale_items.product_id', '=', 'products.id')
            ->select(
                DB::raw("
                    CASE
                        WHEN sale_items.item_name LIKE 'Servicio de Hospedaje%' THEN 'Servicio de Hospedaje'
                        WHEN sale_items.product_id IS NOT NULL THEN COALESCE(products.name, sale_items.item_name)
                        ELSE sale_items.item_name
                    END as grouped_name
                "),
                DB::raw('SUM(sale_items.quantity) as total_quantity')
            )
            ->whereHas('sale', function ($query) use ($tenantId, $startOfMonth, $endOfMonth) {
                $query->where('tenant_id', $tenantId)
                    ->whereBetween('sold_at', [$startOfMonth, $endOfMonth])
                    ->where('status', '!=', 'canceled')
                    ->where('sunat_status', '!=', 'rejected');
            })
            ->groupBy('grouped_name')
            ->orderBy('total_quantity', 'desc')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Unidades / Noches',
                    'data' => $topProducts->pluck('total_quantity')->toArray(),
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#047857',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $topProducts->pluck('grouped_name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
