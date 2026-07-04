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
                ->visible(
                    fn(): bool =>
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

    protected function beforeSave(): void
    {
        $items = collect($this->data['items'] ?? [])
            ->filter(function (array $item): bool {
                return filled($item['product_id'] ?? null)
                    && (float) ($item['quantity'] ?? 0) > 0
                    && (float) ($item['unit_cost'] ?? 0) > 0;
            });

        if ($items->isEmpty()) {
            Notification::make()
                ->title('No puedes guardar una compra vacía')
                ->body('Agrega al menos un producto con cantidad y costo antes de guardar los cambios.')
                ->warning()
                ->persistent()
                ->send();

            $this->halt();
        }

        if ((float) ($this->data['total'] ?? 0) <= 0) {
            Notification::make()
                ->title('El total de la compra no es válido')
                ->body('Verifica los productos, cantidades y costos antes de guardar.')
                ->warning()
                ->persistent()
                ->send();

            $this->halt();
        }

        $this->data['subtotal'] = (float) ($this->data['subtotal'] ?? 0);
        $this->data['igv'] = (float) ($this->data['igv'] ?? 0);
        $this->data['total'] = (float) ($this->data['total'] ?? 0);
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
