<?php

namespace App\Filament\Resources\DeliveryZoneResource\Pages;

use App\Filament\Resources\DeliveryZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDeliveryZone extends CreateRecord
{
    protected static string $resource = DeliveryZoneResource::class;

    // 🌟 ESTA ES LA MAGIA PARA PÁGINAS COMPLETAS
    // Interceptamos los datos del formulario justo antes de guardarlos en la BD
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Inyectamos el ID del negocio (Tenant) de forma silenciosa y segura
        $data['tenant_id'] = Auth::user()->tenant_id;

        return $data;
    }
}
