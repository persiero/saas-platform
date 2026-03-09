<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    public function getTitle(): string
    {
        return 'Registrar Nuevo Gasto';
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
        return 'Gasto registrado exitosamente';
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Gasto registrado')
            ->body('El gasto ha sido registrado correctamente en el sistema.')
            ->icon('heroicon-o-check-circle');
    }
}
