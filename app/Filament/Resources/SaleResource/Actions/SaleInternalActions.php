<?php

namespace App\Filament\Resources\SaleResource\Actions;

use Filament\Forms;
use Filament\Tables;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Sale;
use Percy\Core\Services\Inventory\InventoryService;
use Percy\Core\Services\Sales\CorrelativeService;

class SaleInternalActions
{
    private static function saleBelongsToClosedCashRegister(Sale $sale): bool
    {
        $sale->loadMissing('cashRegister');

        return $sale->cashRegister?->status === 'closed';
    }

    private static function saleBelongsToOpenCashRegister(Sale $sale): bool
    {
        $sale->loadMissing('cashRegister');

        return $sale->cash_register_id !== null
            && $sale->cashRegister?->status === 'open';
    }

    public static function get(): array
    {
        return [
            Tables\Actions\ViewAction::make()
                ->label('Ver Detalle')
                ->icon('heroicon-o-eye')
                ->color('info'),

            Tables\Actions\Action::make('convertToBoleta')
                ->label('Convertir a Boleta')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('success')
                ->visible(
                    fn(Sale $record) =>
                    $record->document_type === '00' &&
                        $record->status !== 'canceled' &&
                        self::saleBelongsToOpenCashRegister($record) &&
                        Auth::user()?->canConvertTicketsToInvoices()
                )
                ->form([
                    Forms\Components\Select::make('serie_boleta')
                        ->label('Seleccione la Serie de Boleta')
                        ->options(function () {
                            return \Percy\Core\Models\Serie::where('tenant_id', Auth::user()->tenant_id)
                                ->where('document_type', '03')
                                ->where('active', true)
                                ->pluck('serie', 'serie');
                        })
                        ->required(),
                ])
                ->action(function (array $data, Sale $record) {
                    if (! self::saleBelongsToOpenCashRegister($record)) {
                        Notification::make()
                            ->danger()
                            ->title('Acción no permitida')
                            ->body('No puedes convertir una venta que pertenece a una caja cerrada o sin caja asociada.')
                            ->send();

                        return;
                    }

                    $originalDocType = $record->document_type;
                    $originalSeries = $record->series;
                    $originalCorrelative = $record->correlative;

                    try {
                        $nuevoCorrelativo = app(CorrelativeService::class)
                            ->next($record->tenant_id, '03', $data['serie_boleta']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->send();

                        return;
                    }

                    $record->update([
                        'document_type' => '03',
                        'series' => $data['serie_boleta'],
                        'correlative' => $nuevoCorrelativo,
                        'sold_at' => now(),
                        'sunat_status' => 'pending',
                        'affected_document_type' => $originalDocType,
                        'affected_document_series' => $originalSeries,
                        'affected_document_correlative' => $originalCorrelative,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Conversión Exitosa')
                        ->body("El ticket ahora es la Boleta {$data['serie_boleta']}-{$nuevoCorrelativo}.")
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Convertir Ticket a Boleta')
                ->modalDescription('El documento pasará a ser una Boleta Electrónica. El stock se mantendrá intacto. ¿Deseas continuar?'),

            Tables\Actions\Action::make('anularTicket')
                ->label('Anular Ticket')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(
                    fn(Sale $record) =>
                    $record->document_type === '00' &&
                        $record->status !== 'canceled' &&
                        self::saleBelongsToOpenCashRegister($record) &&
                        Auth::user()?->canCancelSales()
                )
                ->form([
                    Forms\Components\TextInput::make('reason')
                        ->label('Motivo de anulación')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data, Sale $record) {
                    if (! self::saleBelongsToOpenCashRegister($record)) {
                        Notification::make()
                            ->danger()
                            ->title('Acción no permitida')
                            ->body('No puedes anular una venta que pertenece a una caja cerrada o sin caja asociada.')
                            ->send();

                        return;
                    }

                    foreach ($record->items as $item) {
                        app(InventoryService::class)->refundStock(
                            $item,
                            (float) $item->quantity,
                            "Anulación Ticket {$record->series}-{$record->correlative}: {$data['reason']}"
                        );
                    }

                    $record->update([
                        'status' => 'canceled',
                        'sunat_description' => 'ANULADO INTERNAMENTE: ' . $data['reason'],
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Ticket Anulado')
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Anular Nota de Venta'),
        ];
    }
}
