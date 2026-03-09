<?php

namespace App\Filament\Resources\ExpenseResource\Widgets;

use Percy\Core\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ExpenseStats extends BaseWidget
{
    protected function getStats(): array
    {
        $tenantId = Auth::user()->tenant_id;

        $mesActual = Expense::where('tenant_id', $tenantId)
            ->whereMonth('expense_date', Carbon::now()->month)
            ->sum('amount');

        $hoy = Expense::where('tenant_id', $tenantId)
            ->whereDate('expense_date', Carbon::today())
            ->sum('amount');

        return [
            Stat::make('Gastos del Mes', 'S/ ' . number_format($mesActual, 2))
                ->description('Total gastado en ' . Carbon::now()->translatedFormat('F'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('danger'),

            Stat::make('Gastos de Hoy', 'S/ ' . number_format($hoy, 2))
                ->description('Salidas de efectivo de hoy')
                ->color('warning'),
        ];
    }
}
