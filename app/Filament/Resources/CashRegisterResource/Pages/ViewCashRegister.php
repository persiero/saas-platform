<?php

namespace App\Filament\Resources\CashRegisterResource\Pages;

use App\Filament\Resources\CashRegisterResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewCashRegister extends ViewRecord
{
    protected static string $resource = CashRegisterResource::class;

    // 🌟 NUEVO: Agregamos las acciones de la cabecera
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Volver a la lista')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                // Apuntamos la URL de regreso a la ruta 'index' (la tabla principal)
                ->url(fn () => $this->getResource()::getUrl('index')),
        ];
    }
}
