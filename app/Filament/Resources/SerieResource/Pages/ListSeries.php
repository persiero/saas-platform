<?php

namespace App\Filament\Resources\SerieResource\Pages;

use App\Filament\Resources\SerieResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListSeries extends ListRecords
{
    protected static string $resource = SerieResource::class;

    protected static ?string $title = 'Control de Series';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva Serie')
                ->icon('heroicon-o-plus-circle')
                ->slideOver() // Se abre lateralmente
                // Auto-inyecta el ID del negocio actual:
                ->mutateFormDataUsing(function (array $data): array {
                    $data['tenant_id'] = Auth::user()->tenant_id;
                    $data['serie'] = strtoupper($data['serie']); // Fuerza mayúsculas
                    return $data;
                }),
        ];
    }
}
