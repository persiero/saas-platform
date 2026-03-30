<?php

namespace App\Filament\Resources\CashRegisterResource\Pages;

use App\Filament\Resources\CashRegisterResource;
use Filament\Resources\Pages\CreateRecord;
use Percy\Core\Models\CashRegister;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CreateCashRegister extends CreateRecord
{
    protected static string $resource = CashRegisterResource::class;

    protected static ?string $title = 'Abrir Caja';

    public function mount(): void
    {
        parent::mount();

        // 🌟 CORRECCIÓN: Buscamos si el LOCAL (Tenant) tiene alguna caja abierta, sin importar qué usuario la abrió.
        $hasOpenCash = CashRegister::where('tenant_id', Auth::user()->tenant_id)
            ->where('status', 'open')
            ->exists();

        if ($hasOpenCash) {
            Notification::make()
                ->title('Caja Ya Abierta')
                ->body('Ya existe una caja abierta en este local. Debes cerrarla (Arqueo) antes de abrir un nuevo turno.')
                ->danger() // Cambié warning() a danger() para que sea rojo y más evidente
                ->persistent()
                ->send();

            $this->redirect(route('filament.admin.resources.cash-registers.index'));
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Auth::user()->tenant_id;
        $data['user_id'] = Auth::id();
        $data['opened_at'] = now();
        $data['status'] = 'open';

        return $data;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Abrir Caja')
                ->icon('heroicon-o-lock-open'),
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
        return 'Caja abierta correctamente';
    }
}
