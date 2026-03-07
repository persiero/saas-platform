<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Percy\Core\Models\Serie;
use Percy\Core\Models\CashRegister;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    public function mount(): void
    {
        parent::mount();

        // Validar que el usuario tenga una caja abierta
        $openCash = CashRegister::where('tenant_id', Auth::user()->tenant_id)
            ->where('user_id', Auth::id())
            ->where('status', 'open')
            ->exists();

        if (!$openCash) {
            Notification::make()
                ->title('Caja Cerrada')
                ->body('Debes abrir una caja antes de realizar ventas.')
                ->danger()
                ->persistent()
                ->send();

            $this->redirect(route('filament.admin.resources.cash-registers.index'));
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $serieRecord = Serie::where('document_type', $data['document_type'])
            ->where('serie', $data['series'])
            ->first();

        if (!$serieRecord) {
            throw new \Exception("La serie seleccionada no está configurada en el sistema.");
        }

        $serieRecord->increment('correlative');
        $data['correlative'] = $serieRecord->correlative;

        return $data;
    }
}
