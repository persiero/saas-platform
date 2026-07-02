<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Filament\Actions\Action;

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



    protected function getFormActions(): array
    {
        return [
            Action::make('create')
                ->label('Registrar')
                ->submit('create'), // importante

            Action::make('createAnother')
                ->label('Registrar y crear otra')
                ->submit('createAnother'),

            Action::make('cancel')
                ->label('Cancelar')
                ->color('gray'),
        ];
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
