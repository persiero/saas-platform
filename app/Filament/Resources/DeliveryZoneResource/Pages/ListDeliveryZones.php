<?php

namespace App\Filament\Resources\DeliveryZoneResource\Pages;

use App\Filament\Resources\DeliveryZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryZones extends ListRecords
{
    protected static string $resource = DeliveryZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva Zona de Reparto')
                ->icon('heroicon-o-plus-circle')
                ->slideOver()
                ->modalWidth('3xl')
                ->modalHeading('Crear Zona de Reparto')
                ->createAnother(false)
                ->mutateFormDataUsing(function (array $data): array {
                    $data['tenant_id'] = \Illuminate\Support\Facades\Auth::user()->tenant_id;

                    unset($data['department_id'], $data['province_id']);

                    return $data;
                }),
            // 🌟 Quitamos el ->slideOver() y el ->mutateFormDataUsing()
            // Al no tenerlos, Filament abrirá automáticamente la página CreateDeliveryZone
        ];
    }
}
