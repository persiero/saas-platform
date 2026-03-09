<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    public function getTitle(): string
    {
        return 'Editar Gasto';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Gasto')
                ->modalDescription('¿Estás seguro de que deseas eliminar este gasto?')
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
        return 'Gasto actualizado exitosamente';
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Gasto actualizado')
            ->body('Los cambios han sido guardados correctamente.')
            ->icon('heroicon-o-check-circle');
    }
}
