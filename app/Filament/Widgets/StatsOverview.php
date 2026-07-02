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
        return Auth::check() && Auth::user()?->tenant_id !== null;
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $userId = Auth::id();

        $baseSalesQuery = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereIn('document_type', ['00', '01', '03'])
            ->where(function ($query) {
                $query->whereNull('sunat_status')
                    ->orWhere('sunat_status', '!=', 'rejected');
            });

        $todaySales = (clone $baseSalesQuery)
            ->whereDate('sold_at', today())
            ->sum('total');

        $todayCount = (clone $baseSalesQuery)
            ->whereDate('sold_at', today())
            ->count();

        $yesterdaySales = (clone $baseSalesQuery)
            ->whereDate('sold_at', today()->subDay())
            ->sum('total');

        $monthSales = (clone $baseSalesQuery)
            ->whereBetween('sold_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->sum('total');

        $monthCount = (clone $baseSalesQuery)
            ->whereBetween('sold_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->count();

        $averageTicket = $todayCount > 0
            ? $todaySales / $todayCount
            : 0;

        $trend = $yesterdaySales > 0
            ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100
            : ($todaySales > 0 ? 100 : 0);

        $openCash = CashRegister::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        $lowStock = Product::where('tenant_id', $tenantId)
            ->where('active', true)
            ->where('type', 'product')
            ->where('current_stock', '<=', 5)
            ->count();

        $totalProducts = Product::where('tenant_id', $tenantId)
            ->where('active', true)
            ->where('type', 'product')
            ->count();

        return [
            Stat::make('Ventas de Hoy', 'S/ ' . number_format($todaySales, 2))
                ->description(
                    $todayCount . ' venta' . ($todayCount !== 1 ? 's' : '') .
                        ' | Ticket prom.: S/ ' . number_format($averageTicket, 2) .
                        ($yesterdaySales > 0 ? ' | ' . ($trend >= 0 ? '+' : '') . number_format($trend, 1) . '% vs ayer' : '')
                )
                ->descriptionIcon($trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trend >= 0 ? 'success' : 'danger')
                ->chart($this->getSalesChart($tenantId)),

            Stat::make('Ventas del Mes', 'S/ ' . number_format($monthSales, 2))
                ->description($monthCount . ' comprobante' . ($monthCount !== 1 ? 's' : '') . ' emitido' . ($monthCount !== 1 ? 's' : ''))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Estado de Caja', $openCash ? 'ABIERTA' : 'CERRADA')
                ->description(
                    $openCash
                        ? 'Apertura: S/ ' . number_format($openCash->opening_amount, 2) . ' | ' . $openCash->opened_at->format('H:i')
                        : 'Abre caja para iniciar operaciones'
                )
                ->descriptionIcon($openCash ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($openCash ? 'success' : 'danger'),

            Stat::make('Inventario', $totalProducts . ' producto' . ($totalProducts !== 1 ? 's' : ''))
                ->description(
                    $lowStock > 0
                        ? $lowStock . ' con stock bajo'
                        : 'Stock saludable'
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

            $sales[] = Sale::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereIn('document_type', ['00', '01', '03'])
                ->where(function ($query) {
                    $query->whereNull('sunat_status')
                        ->orWhere('sunat_status', '!=', 'rejected');
                })
                ->whereDate('sold_at', $date)
                ->sum('total');
        }

        return $sales;
    }
}
