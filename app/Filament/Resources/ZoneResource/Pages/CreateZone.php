<?php

namespace App\Filament\Resources\ZoneResource\Pages;

use App\Filament\Resources\ZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateZone extends CreateRecord
{
    protected static string $resource = ZoneResource::class;

    protected static ?string $title = 'Crear Zona / Piso';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Volver a la lista')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                // Apuntamos la URL de regreso a la ruta 'index' (la tabla principal)
                ->url(fn() => $this->getResource()::getUrl('index')),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Crear Zona / Piso')
                ->icon('heroicon-o-check-circle'),

            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = \Illuminate\Support\Facades\Auth::user()->tenant_id;
        return $data;
    }
}
