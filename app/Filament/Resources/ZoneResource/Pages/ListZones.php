<?php

namespace App\Filament\Resources\ZoneResource\Pages;

use App\Filament\Resources\ZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListZones extends ListRecords
{
    protected static string $resource = ZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva Zona / Piso')
                ->icon('heroicon-o-plus-circle')
                ->slideOver()
                ->modalWidth('3xl')
                ->createAnother(false),
        ];
    }
}
