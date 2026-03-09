<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

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
        $data['tenant_id'] = Auth::user()->tenant_id;
        return $data;
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
