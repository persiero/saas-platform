<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    public function getTitle(): string
    {
        return 'Editar Proveedor';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Proveedor')
                ->modalDescription('¿Estás seguro de que deseas eliminar este proveedor?')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->modalCancelActionLabel('Cancelar'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Proveedor actualizado exitosamente';
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Proveedor actualizado')
            ->body('Los cambios han sido guardados correctamente.')
            ->icon('heroicon-o-check-circle');
    }
}
