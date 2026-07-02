<?php

namespace App\Filament\Resources\SaleResource\Actions;

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
                ->icon('heroicon-m-ellipsis-vertical')
                ->button()
                ->color('gray'),
        ];
    }
}
