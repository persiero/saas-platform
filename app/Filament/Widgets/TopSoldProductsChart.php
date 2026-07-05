<?php

namespace App\Filament\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Percy\Core\Models\SaleItem;

class TopSoldProductsChart extends ChartWidget
{
    protected static ?string $heading = 'Top 5 Productos y Servicios del Mes';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'xl' => 1,
    ];

    protected static ?string $maxHeight = '360px';

    public static function canView(): bool
    {
        return Auth::check() && Auth::user()?->tenant_id !== null;
    }

    protected function getData(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

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
                    ->where('status', 'completed')
                    ->whereIn('document_type', ['00', '01', '03'])
                    ->where(function ($query) {
                        $query->whereNull('sunat_status')
                            ->orWhere('sunat_status', '!=', 'rejected');
                    });
            })
            ->groupBy('grouped_name')
            ->orderBy('total_quantity', 'desc')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Cantidad vendida',
                    'data' => $topProducts
                        ->pluck('total_quantity')
                        ->map(fn($value) => round((float) $value, 2))
                        ->toArray(),
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $topProducts->pluck('grouped_name')->toArray(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<JS
        {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' Cantidad vendida: ' + context.parsed.x;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    },
                    grid: {
                        display: true
                    }
                },
                y: {
                    ticks: {
                        autoSkip: false,
                        callback: function(value) {
                            const label = this.getLabelForValue(value);
                            const maxLength = window.innerWidth < 640 ? 18 : 30;

                            if (label.length > maxLength) {
                                return label.substring(0, maxLength) + '…';
                            }

                            return label;
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
