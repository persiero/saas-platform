<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    protected static ?string $title = 'Detalle de la Compra';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Editar Compra')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn(): bool => PurchaseResource::canEdit($this->record)),
        ];
    }
}
