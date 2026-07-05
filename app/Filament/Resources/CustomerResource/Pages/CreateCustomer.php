<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'Nuevo Cliente';

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
                ->label('Registrar Cliente')
                ->icon('heroicon-o-check-circle'),

            $this->getCreateAnotherFormAction()
                ->label('Registrar y crear otro')
                ->icon('heroicon-o-plus-circle'),

            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Cliente registrado correctamente';
    }
}
