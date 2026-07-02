<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Percy\Core\Models\CashRegister;
use Illuminate\Validation\ValidationException;

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
        $openCashRegister = CashRegister::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if (! $openCashRegister) {
            throw ValidationException::withMessages([
                'cash_register_id' => 'Debes abrir una caja antes de registrar gastos.',
            ]);
        }

        $data['tenant_id'] = Auth::user()->tenant_id;
        $data['user_id'] = Auth::id();
        $data['cash_register_id'] = $openCashRegister->id;

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
