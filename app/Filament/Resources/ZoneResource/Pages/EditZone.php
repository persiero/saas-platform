<?php

namespace App\Filament\Resources\ZoneResource\Pages;

use App\Filament\Resources\ZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditZone extends EditRecord
{
    protected static string $resource = ZoneResource::class;

    protected static ?string $title = 'Editar Zona / Piso';

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
            $this->getSaveFormAction()
                ->label('Guardar Cambios')
                ->icon('heroicon-o-check-circle'),

            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
