<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Percy\Core\Models\Sale;
use Percy\Core\Models\CashRegister;
use Percy\Core\Models\Product;
use Percy\Core\Models\ProductBatch;
use Percy\Core\Models\Expense;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\SaleResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ExpenseResource;
use Percy\Core\Services\Tenants\TenantPlanService;


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

        $hasOnlineStore = self::tenantHasOnlineStore();
        $hasCashRegister = self::tenantPlanHas('has_cash_register');
        $hasBasicInventory = self::tenantPlanHas('has_basic_inventory');
        $hasExpenses = self::tenantPlanHas('has_expenses');
        $hasExpiryDates = self::tenantHasExpiryDates();

        $pendingWebOrders = 0;
        $pendingWebAmount = 0;

        $completedWebOrdersToday = 0;
        $completedWebAmountToday = 0;

        if ($hasOnlineStore) {
            $pendingWebOrders = Sale::query()
                ->where('tenant_id', $tenantId)
                ->where('channel', 'ecommerce')
                ->where('status', 'pending_payment')
                ->count();

            $pendingWebAmount = Sale::query()
                ->where('tenant_id', $tenantId)
                ->where('channel', 'ecommerce')
                ->where('status', 'pending_payment')
                ->sum('total');

            $completedWebOrdersToday = Sale::query()
                ->where('tenant_id', $tenantId)
                ->where('channel', 'ecommerce')
                ->where('status', 'completed')
                ->whereIn('document_type', ['00', '01', '03'])
                ->where(function ($query) {
                    $query->whereNull('sunat_status')
                        ->orWhere('sunat_status', '!=', 'rejected');
                })
                ->whereDate('sold_at', today())
                ->count();

            $completedWebAmountToday = Sale::query()
                ->where('tenant_id', $tenantId)
                ->where('channel', 'ecommerce')
                ->where('status', 'completed')
                ->whereIn('document_type', ['00', '01', '03'])
                ->where(function ($query) {
                    $query->whereNull('sunat_status')
                        ->orWhere('sunat_status', '!=', 'rejected');
                })
                ->whereDate('sold_at', today())
                ->sum('total');
        }

        $monthExpenses = 0;
        $monthExpensesCount = 0;

        if ($hasExpenses) {
            $monthExpenses = Expense::query()
                ->where('tenant_id', $tenantId)
                ->whereBetween('expense_date', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ])
                ->sum('amount');

            $monthExpensesCount = Expense::query()
                ->where('tenant_id', $tenantId)
                ->whereBetween('expense_date', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ])
                ->count();
        }

        $expiredBatches = 0;
        $expiringSoonBatches = 0;

        if ($hasExpiryDates) {
            $expiredBatches = ProductBatch::query()
                ->where('tenant_id', $tenantId)
                ->where('current_quantity', '>', 0)
                ->whereDate('expiration_date', '<', today())
                ->count();

            $expiringSoonBatches = ProductBatch::query()
                ->where('tenant_id', $tenantId)
                ->where('current_quantity', '>', 0)
                ->whereDate('expiration_date', '>=', today())
                ->whereDate('expiration_date', '<=', today()->addDays(90))
                ->count();
        }

        $stats = [
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
        ];

        if ($hasCashRegister) {
            $stats[] = Stat::make('Estado de Caja', $openCash ? 'ABIERTA' : 'CERRADA')
                ->description(
                    $openCash
                        ? 'Apertura: S/ ' . number_format($openCash->opening_amount, 2) . ' | ' . $openCash->opened_at->format('H:i')
                        : 'Abre caja para iniciar operaciones'
                )
                ->descriptionIcon($openCash ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($openCash ? 'success' : 'danger');
        }

        if ($hasBasicInventory) {
            $stats[] = Stat::make('Inventario', $totalProducts . ' producto' . ($totalProducts !== 1 ? 's' : ''))
                ->description(
                    $lowStock > 0
                        ? $lowStock . ' con stock bajo'
                        : 'Stock saludable'
                )
                ->descriptionIcon($lowStock > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($lowStock > 0 ? 'warning' : 'success')
                ->url(ProductResource::getUrl('index'));
        }

        if ($hasExpiryDates) {
            $totalExpiryAlerts = $expiredBatches + $expiringSoonBatches;

            $stats[] = Stat::make('Vencimientos Próximos', $totalExpiryAlerts)
                ->description(
                    $expiredBatches > 0
                        ? $expiredBatches . ' vencido' . ($expiredBatches !== 1 ? 's' : '') . ' | ' . $expiringSoonBatches . ' por vencer'
                        : ($expiringSoonBatches > 0
                            ? $expiringSoonBatches . ' lote' . ($expiringSoonBatches !== 1 ? 's' : '') . ' por vencer en 90 días'
                            : 'Sin vencimientos críticos'
                        )
                )
                ->descriptionIcon(
                    $expiredBatches > 0
                        ? 'heroicon-m-exclamation-circle'
                        : ($expiringSoonBatches > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                )
                ->color(
                    $expiredBatches > 0
                        ? 'danger'
                        : ($expiringSoonBatches > 0 ? 'warning' : 'success')
                )
                ->url(ProductResource::getUrl('index'));
        }

        if ($hasExpenses) {
            $stats[] = Stat::make('Gastos del Mes', 'S/ ' . number_format($monthExpenses, 2))
                ->description($monthExpensesCount . ' gasto' . ($monthExpensesCount !== 1 ? 's' : '') . ' registrado' . ($monthExpensesCount !== 1 ? 's' : ''))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($monthExpenses > 0 ? 'danger' : 'success')
                ->url(ExpenseResource::getUrl('index'));
        }

        if ($hasOnlineStore) {
            $stats[] = Stat::make('Pedidos Web Pendientes', $pendingWebOrders)
                ->description('S/ ' . number_format($pendingWebAmount, 2) . ' por procesar')
                ->descriptionIcon($pendingWebOrders > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($pendingWebOrders > 0 ? 'warning' : 'success')
                ->url(SaleResource::getUrl('index'));

            $stats[] = Stat::make('Ventas Online Hoy', 'S/ ' . number_format($completedWebAmountToday, 2))
                ->description($completedWebOrdersToday . ' pedido' . ($completedWebOrdersToday !== 1 ? 's' : '') . ' procesado' . ($completedWebOrdersToday !== 1 ? 's' : ''))
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info')
                ->url(SaleResource::getUrl('index'));
        }

        return $stats;
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

    private static function tenantPlanHas(string $feature): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            return false;
        }

        return app(TenantPlanService::class)->has($feature, $user->tenant);
    }

    private static function tenantHasOnlineStore(): bool
    {
        return self::tenantPlanHas('has_online_store')
            || self::tenantPlanHas('has_web_orders');
    }

    private static function tenantHasExpiryDates(): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->tenant || ! $user->tenant->businessSector) {
            return false;
        }

        $features = $user->tenant->businessSector->features ?? [];

        return (bool) ($features['has_expiry_dates'] ?? false);
    }
}
