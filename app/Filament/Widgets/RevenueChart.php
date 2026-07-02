<?php

namespace App\Filament\Widgets;

use Percy\Core\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Evolución de Ingresos (Últimos 14 días)';
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return Auth::check() && Auth::user()?->tenant_id !== null;
    }

    protected function getData(): array
    {
        $data = [];
        $labels = [];
        $tenantId = Auth::user()->tenant_id;

        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d M');

            $sum = Sale::where('tenant_id', $tenantId)
                ->whereDate('sold_at', $date->toDateString())
                ->where('status', 'completed')
                ->whereIn('document_type', ['00', '01', '03'])
                ->where(function ($query) {
                    $query->whereNull('sunat_status')
                        ->orWhere('sunat_status', '!=', 'rejected');
                })
                ->sum('total');

            $data[] = $sum;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ventas S/',
                    'data' => $data,
                    'fill' => 'start',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
