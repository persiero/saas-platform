<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    public function getTitle(): string
    {
        return 'Registrar Nueva Compra';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!Auth::user()?->canCreatePurchases()) {
            abort(403);
        }

        $data['tenant_id'] = Auth::user()->tenant_id;

        return $data;
    }

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
                ->label('Registrar Compra')
                ->icon('heroicon-o-check-circle'),

            $this->getCreateAnotherFormAction()
                ->label('Registrar y crear otra')
                ->icon('heroicon-o-plus-circle'),

            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    protected function beforeCreate(): void
    {
        $items = collect($this->data['items'] ?? [])
            ->filter(function (array $item): bool {
                return filled($item['product_id'] ?? null)
                    && (float) ($item['quantity'] ?? 0) > 0
                    && (float) ($item['unit_cost'] ?? 0) > 0;
            });

        if ($items->isEmpty()) {
            Notification::make()
                ->title('No puedes registrar una compra vacía')
                ->body('Agrega al menos un producto con cantidad y costo antes de registrar la compra.')
                ->warning()
                ->persistent()
                ->send();

            $this->halt();
        }

        if ((float) ($this->data['total'] ?? 0) <= 0) {
            Notification::make()
                ->title('El total de la compra no es válido')
                ->body('Verifica los productos, cantidades y costos antes de registrar la compra.')
                ->warning()
                ->persistent()
                ->send();

            $this->halt();
        }

        $this->data['subtotal'] = (float) ($this->data['subtotal'] ?? 0);
        $this->data['igv'] = (float) ($this->data['igv'] ?? 0);
        $this->data['total'] = (float) ($this->data['total'] ?? 0);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Compra registrada exitosamente';
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Compra registrada')
            ->body('La compra ha sido registrada correctamente en el sistema.')
            ->icon('heroicon-o-check-circle');
    }
}
