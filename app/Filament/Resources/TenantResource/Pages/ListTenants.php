<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Cliente SaaS')
                ->icon('heroicon-o-plus-circle')
                ->modalHeading('Crear Cliente SaaS')
                ->slideOver()
                ->modalWidth('4xl')
                ->createAnother(false),
        ];
    }
}
