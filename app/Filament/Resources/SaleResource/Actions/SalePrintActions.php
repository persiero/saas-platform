<?php

namespace App\Filament\Resources\SaleResource\Actions;

use Filament\Support\Enums\ActionSize;
use Filament\Tables;
use Percy\Core\Models\Sale;

class SalePrintActions
{
    public static function get(): array
    {
        return [
            Tables\Actions\Action::make('print')
                ->label('Ticket')
                ->tooltip('Imprimir ticket')
                ->icon('heroicon-o-printer')
                ->iconButton()
                ->size(ActionSize::Large)
                ->color('info')
                ->url(fn(Sale $record): string => route('percy.print.ticket', $record))
                ->openUrlInNewTab(),
        ];
    }
}
