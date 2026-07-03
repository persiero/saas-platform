<?php

namespace App\Filament\Resources\SaleResource\Actions;

use Filament\Support\Enums\ActionSize;
use Filament\Tables;

class SaleTableActions
{
    public static function get(): array
    {
        return [
            ...SalePrintActions::get(),

            Tables\Actions\ActionGroup::make([
                ...SaleEcommerceActions::get(),
                ...SaleInternalActions::get(),
                ...SaleSunatActions::get(),
            ])
                ->label('Opciones')
                ->tooltip('Opciones')
                ->icon('heroicon-m-ellipsis-vertical')
                ->iconButton()
                ->size(ActionSize::Large)
                ->color('gray'),
        ];
    }
}
