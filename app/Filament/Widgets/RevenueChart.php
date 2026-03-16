<?php

namespace App\Filament\Widgets;

use Percy\Core\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Ingresos Diarios (Últimos 7 días)';
    protected static ?int $sort = 3; // Lo ubicamos debajo de las alertas de vencimiento
    protected int | string | array $columnSpan = 'full'; // Que ocupe todo el ancho
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = [];
        $labels = [];
        $tenantId = Auth::user()->tenant_id;

        // Recorremos los últimos 7 días con un bucle para armar los datos
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d M'); // Ej: 14 Mar

            // Sumamos las ventas de ese día exacto para este tenant
            $sum = Sale::where('tenant_id', $tenantId)
                ->whereDate('sold_at', $date->toDateString())
                ->where('sunat_status', '!=', 'rejected') // Ignoramos las facturas rechazadas por SUNAT
                ->sum('total');

            $data[] = $sum;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ventas S/',
                    'data' => $data,
                    'backgroundColor' => '#3b82f6', // Azul brillante matching con "Nueva Venta"
                    'borderColor' => '#1d4ed8',
                    'fill' => 'start', // Efecto de área coloreada
                    'tension' => 0.3, // Suaviza la línea
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
