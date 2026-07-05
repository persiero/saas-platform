<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    public function getTitle(): string
    {
        return 'Proveedores';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Proveedor')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->slideOver()
                ->modalWidth('3xl')
                ->createAnother(false),
        ];
    }
}
