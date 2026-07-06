<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected static ?string $title = 'Configurar Cliente SaaS';

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Guardar Cambios')
                ->icon('heroicon-o-check-circle'),

            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
