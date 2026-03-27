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

    public static function canView(): bool
    {
        return \Illuminate\Support\Facades\Auth::user()->tenant_id !== null;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $userId = Auth::id();

        // 🌟 CORRECCIÓN 1 Y 3: Creamos una consulta base.
        // Eliminamos la restricción de SUNAT 'accepted' porque una venta es una venta aunque esté 'pending'.
        $baseSalesQuery = Sale::where('tenant_id', $tenantId)
            ->where('status', '!=', 'canceled')
            ->whereIn('document_type', ['00', '01', '03']);

        // Ventas de hoy (Suma de dinero)
        $todaySales = (clone $baseSalesQuery)->whereDate('sold_at', today())->sum('total');

        // Ventas de hoy (Cantidad de tickets) 🌟 CORRECCIÓN 1: Usamos count()
        $todayCount = (clone $baseSalesQuery)->whereDate('sold_at', today())->count();

        // Ventas de ayer para comparación 🌟 CORRECCIÓN 2: Usamos today()->subDay()
        $yesterdaySales = (clone $baseSalesQuery)->whereDate('sold_at', today()->subDay())->sum('total');

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
        $sales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            // 🌟 CORRECCIÓN 2: Evaluamos la fecha de cada día del bucle, no today()
            $sales[] = Sale::where('tenant_id', $tenantId)
                ->where('status', '!=', 'canceled')
                ->whereIn('document_type', ['00', '01', '03'])
                ->whereDate('sold_at', $date)
                ->sum('total');
        }
        return $sales;
    }
}
