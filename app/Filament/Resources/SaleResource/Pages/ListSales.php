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
        // 1. Verificamos si el usuario logueado tiene una caja abierta en este momento
        $hasOpenRegister = \Percy\Core\Models\CashRegister::where('tenant_id', Auth::user()->tenant_id)
            ->where('user_id', Auth::id())
            ->where('status', 'open')
            ->exists();

        // 2. Si NO tiene caja abierta, cambiamos el botón
        if (!$hasOpenRegister) {
            return [
                \Filament\Actions\Action::make('abrirCaja')
                    ->label('Abrir Caja para Vender')
                    ->color('warning') // Color naranja/amarillo para llamar la atención
                    ->icon('heroicon-o-lock-closed')
                    // Lo redirigimos mágicamente al módulo de Cajas
                    ->url(\App\Filament\Resources\CashRegisterResource::getUrl('index')),
            ];
        }

        // 3. Si SÍ tiene caja abierta, mostramos el botón normal de Nueva Venta
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Nueva Venta')
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
