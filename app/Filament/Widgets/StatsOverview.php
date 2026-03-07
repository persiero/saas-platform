<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Percy\Core\Models\Sale;
use Percy\Core\Models\CashRegister;
use Percy\Core\Models\Product;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }
    protected function getStats(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $userId = Auth::id();

        // Ventas de hoy
        $todaySales = Sale::where('tenant_id', $tenantId)
            ->whereIn('document_type', ['01', '03'])
            ->whereDate('sold_at', today())
            ->where('sunat_status', 'accepted')
            ->sum('total');

        $todayCount = Sale::where('tenant_id', $tenantId)
            ->whereIn('document_type', ['01', '03'])
            ->whereDate('sold_at', today())
            ->where('sunat_status', 'accepted')
            ->count();

        // Ventas de ayer para comparación
        $yesterdaySales = Sale::where('tenant_id', $tenantId)
            ->whereIn('document_type', ['01', '03'])
            ->whereDate('sold_at', today()->subDay())
            ->where('sunat_status', 'accepted')
            ->sum('total');

        // Estado de caja
        $openCash = CashRegister::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        // Productos con stock bajo
        $lowStock = Product::where('tenant_id', $tenantId)
            ->where('active', true)
            ->get()
            ->filter(fn($p) => $p->current_stock <= 5)
            ->count();

        // Total de productos activos
        $totalProducts = Product::where('tenant_id', $tenantId)
            ->where('active', true)
            ->count();

        // Calcular tendencia de ventas
        $trend = $yesterdaySales > 0 
            ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100 
            : ($todaySales > 0 ? 100 : 0);

        return [
            Stat::make('Ventas de Hoy', 'S/ ' . number_format($todaySales, 2))
                ->description(
                    $todayCount . ' comprobante' . ($todayCount != 1 ? 's' : '') . ' emitido' . ($todayCount != 1 ? 's' : '')
                    . ($yesterdaySales > 0 ? ' | ' . ($trend >= 0 ? '+' : '') . number_format($trend, 1) . '% vs ayer' : '')
                )
                ->descriptionIcon($trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trend >= 0 ? 'success' : 'danger')
                ->chart($this->getSalesChart($tenantId)),

            Stat::make('Estado de Caja', $openCash ? 'ABIERTA' : 'CERRADA')
                ->description(
                    $openCash 
                        ? 'Apertura: S/ ' . number_format($openCash->opening_amount, 2) . ' | ' . $openCash->opened_at->format('H:i')
                        : 'Debes abrir caja para iniciar ventas'
                )
                ->descriptionIcon($openCash ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($openCash ? 'success' : 'danger'),

            Stat::make('Inventario', $totalProducts . ' producto' . ($totalProducts != 1 ? 's' : ''))
                ->description(
                    $lowStock > 0 
                        ? $lowStock . ' con stock bajo (≤ 5 unidades)' 
                        : 'Stock saludable en todos los productos'
                )
                ->descriptionIcon($lowStock > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($lowStock > 0 ? 'warning' : 'success'),
        ];
    }

    protected function getSalesChart(int $tenantId): array
    {
        // Últimos 7 días de ventas para el mini gráfico
        $sales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $sales[] = Sale::where('tenant_id', $tenantId)
                ->whereIn('document_type', ['01', '03'])
                ->whereDate('sold_at', $date)
                ->where('sunat_status', 'accepted')
                ->sum('total');
        }
        return $sales;
    }
}
