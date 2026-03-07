<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Percy\Core\Models\CashRegister;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected static ?string $title = 'Ventas';

    protected ?string $maxContentWidth = 'full';

    public function mount(): void
    {
        parent::mount();

        $openCash = CashRegister::where('tenant_id', Auth::user()->tenant_id)
            ->where('user_id', Auth::id())
            ->where('status', 'open')
            ->exists();

        if (!$openCash) {
            Notification::make()
                ->title('Caja Cerrada')
                ->body('Recuerda abrir una caja antes de realizar ventas.')
                ->warning()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $openCash = CashRegister::where('tenant_id', Auth::user()->tenant_id)
            ->where('user_id', Auth::id())
            ->where('status', 'open')
            ->exists();

        return [
            Actions\CreateAction::make()
                ->label('Nueva Venta')
                ->disabled(!$openCash)
                ->tooltip(!$openCash ? 'Debes abrir una caja primero' : null),
        ];
    }
}
