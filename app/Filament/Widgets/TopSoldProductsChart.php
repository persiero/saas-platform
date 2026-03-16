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

        // Consulta SQL profesional: Suma cantidades de items vendidos este mes, agrupa por producto y ordena
        $topProducts = SaleItem::select('item_name', DB::raw('SUM(quantity) as total_quantity'))
            ->whereHas('sale', function ($query) use ($tenantId, $startOfMonth, $endOfMonth) {
                $query->where('tenant_id', $tenantId)
                    ->whereBetween('sold_at', [$startOfMonth, $endOfMonth])
                    ->where('sunat_status', '!=', 'rejected'); // Solo facturas válidas
            })
            ->groupBy('item_name')
            ->orderBy('total_quantity', 'desc')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Unidades Vendidas',
                    'data' => $topProducts->pluck('total_quantity')->toArray(),
                    'backgroundColor' => '#10b981', // Verde esmeralda matching con "Abrir Caja"
                    'borderColor' => '#047857',
                    'borderRadius' => 6, // Esquinas redondeadas en las barras
                ],
            ],
            'labels' => $topProducts->pluck('item_name')->toArray(), // Nombres de las medicinas
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
