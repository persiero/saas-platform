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
        // 1. Verificamos si el negocio es un Restaurante
        $features = Auth::user()->tenant->businessSector->features ?? [];
        $isRestaurant = $features['has_tables'] ?? false;

        // 2. Verificamos si el usuario logueado tiene una caja abierta en este momento
        $hasOpenRegister = \Percy\Core\Models\CashRegister::where('tenant_id', Auth::user()->tenant_id)
            ->where('user_id', Auth::id())
            ->where('status', 'open')
            ->exists();

        // 3. Si NO tiene caja abierta, bloqueamos la venta para TODOS (Farmacias y Restaurantes)
        if (!$hasOpenRegister) {
            return [
                \Filament\Actions\Action::make('abrirCaja')
                    ->label('Abrir Caja para Vender')
                    ->color('warning')
                    ->icon('heroicon-o-lock-closed')
                    ->url(\App\Filament\Resources\CashRegisterResource::getUrl('index')),
            ];
        }

        // 4. Si TIENE caja abierta, pero ES RESTAURANTE: Lo mandamos al Mapa de Mesas
        if ($isRestaurant) {
            return [
                \Filament\Actions\Action::make('irMapaMesas')
                    ->label('Ir a Atención de Mesas')
                    ->color('primary')
                    ->icon('heroicon-o-squares-2x2')
                    ->url(\App\Filament\Pages\PosRestaurant::getUrl()),
            ];
            // Nota: Si prefieres que simplemente no haya NINGÚN botón,
            // borra la acción de arriba y deja solo: return [];
        }

        // 5. Si TIENE caja abierta y NO es restaurante (Ej: Farmacia): Mostramos el botón normal
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Nueva Venta')
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
