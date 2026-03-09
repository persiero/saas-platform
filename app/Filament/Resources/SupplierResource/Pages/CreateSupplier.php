<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    public function getTitle(): string
    {
        return 'Registrar Nuevo Proveedor';
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
        return 'Proveedor registrado exitosamente';
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Proveedor registrado')
            ->body('El proveedor ha sido registrado correctamente en el sistema.')
            ->icon('heroicon-o-check-circle');
    }
}
