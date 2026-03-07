<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use Filament\Resources\Resource;

class ReportResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Reportes';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?int $navigationSort = 33;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ViewReports::route('/'),
        ];
    }
}
