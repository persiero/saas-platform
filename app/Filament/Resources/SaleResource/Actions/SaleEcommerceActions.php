<?php

namespace App\Filament\Resources\SaleResource\Actions;

use Filament\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Sale;
use Percy\Core\Services\Sales\CorrelativeService;

class SaleEcommerceActions
{
    public static function get(): array
    {
        return [
            Tables\Actions\EditAction::make()
                ->label('Procesar Pedido')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn(Sale $record) => $record->channel === 'ecommerce' && $record->status === 'pending_payment')
                ->using(function (Model $record, array $data): Model {

                    $data['status'] = 'completed';
                    $data['user_id'] = Auth::id();

                    $tipoDoc = $data['document_type'] ?? $record->document_type;

                    if (in_array($tipoDoc, ['01', '03'], true)) {
                        $data['sunat_status'] = 'pending';
                    } else {
                        unset($data['sunat_status']);
                    }

                    $tipoNuevo = $data['document_type'] ?? $record->document_type;
                    $serieNueva = $data['series'] ?? $record->series;

                    if ($record->document_type !== $tipoNuevo || $record->series !== $serieNueva || empty($record->correlative)) {
                        $data['correlative'] = app(CorrelativeService::class)
                            ->next($record->tenant_id, $tipoNuevo, $serieNueva);
                    }

                    $record->forceFill($data);
                    $record->save();

                    return $record;
                }),
        ];
    }
}
