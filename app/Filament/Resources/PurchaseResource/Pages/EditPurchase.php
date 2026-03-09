<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditPurchase extends EditRecord
{
    protected static string $resource = PurchaseResource::class;

    public function getTitle(): string
    {
        return 'Editar Compra';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Compra')
                ->modalDescription('¿Estás seguro de que deseas eliminar esta compra?')
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
        return 'Compra actualizada exitosamente';
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Compra actualizada')
            ->body('Los cambios han sido guardados correctamente.')
            ->icon('heroicon-o-check-circle');
    }
}
