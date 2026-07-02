<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Nuevo Producto';

    protected function beforeCreate(): void
    {
        if (ProductResource::tenantHasAvailableProductSlots()) {
            return;
        }

        Notification::make()
            ->title('Límite de productos alcanzado')
            ->body(ProductResource::productLimitMessage())
            ->warning()
            ->persistent()
            ->send();

        throw ValidationException::withMessages([
            'limit' => ProductResource::productLimitMessage(),
        ]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Guardar Producto')
                ->icon('heroicon-o-check-circle'),
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
        return 'Producto creado correctamente';
    }
}
