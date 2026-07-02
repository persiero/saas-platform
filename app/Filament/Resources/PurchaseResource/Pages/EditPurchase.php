<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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
                ->color('danger')
                ->visible(fn (): bool =>
                    $this->record->status === 'pending' &&
                    (Auth::user()?->canDeletePurchases() ?? false)
                )
                ->requiresConfirmation()
                ->modalHeading('Eliminar Compra Pendiente')
                ->modalDescription('Esta compra aún no afecta inventario. ¿Deseas eliminarla?')
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

    // 🌟 NUEVO: Si editan la compra y agregan un vencimiento, aseguramos que exista en el inventario

}
