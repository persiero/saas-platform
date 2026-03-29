<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use Filament\Resources\Resource;

class ReportResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?string $modelLabel = 'Reporte';
    protected static ?string $pluralModelLabel = 'Reportes';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        // 1. tenant_id !== null (Bloquea al Súper Admin para que no exploten las gráficas)
        // 2. isAdmin() (Bloquea a los empleados normales para proteger las finanzas)
        return $user->tenant_id !== null && $user->isAdmin();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ViewReports::route('/'),
        ];
    }
}
