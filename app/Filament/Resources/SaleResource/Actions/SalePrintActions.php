<?php

namespace App\Filament\Resources\SaleResource\Actions;

use Filament\Tables;
use Percy\Core\Models\Sale;

class SalePrintActions
{
    public static function get(): array
    {
        return [
            Tables\Actions\Action::make('print')
                ->label('Ticket')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn(Sale $record): string => route('percy.print.ticket', $record))
                ->openUrlInNewTab(),
        ];
    }
}
