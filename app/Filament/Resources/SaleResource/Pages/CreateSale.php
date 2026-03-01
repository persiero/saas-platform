<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Percy\Core\Models\Serie;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    // MAGIA PURA: Interceptamos los datos antes de que se guarden en la BD
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 1. Buscamos la serie en la configuración
        $serieRecord = Serie::where('document_type', $data['document_type'])
            ->where('serie', $data['series'])
            ->first();

        if (!$serieRecord) {
            // Si el cajero intenta usar una serie que no existe, bloqueamos la operación
            throw new \Exception("La serie seleccionada no está configurada en el sistema.");
        }

        // 2. Sumamos 1 al correlativo de forma segura
        $serieRecord->increment('correlative');

        // 3. Inyectamos ese nuevo número en la venta
        $data['correlative'] = $serieRecord->correlative;

        return $data;
    }
}
