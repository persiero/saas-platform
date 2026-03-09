<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

    public function getTitle(): string
    {
        return 'Compras';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva Compra')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
