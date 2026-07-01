<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Services\Tenants\TenantPlanService;

class ReportResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?string $modelLabel = 'Reporte';
    protected static ?string $pluralModelLabel = 'Reportes';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || $user->tenant_id === null || ! $user->isAdmin()) {
            return false;
        }

        return app(TenantPlanService::class)->has('has_basic_reports', $user->tenant)
            || app(TenantPlanService::class)->has('has_profitability_reports', $user->tenant)
            || app(TenantPlanService::class)->has('has_advanced_reports', $user->tenant);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ViewReports::route('/'),
        ];
    }
}
