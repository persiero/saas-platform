<?php

namespace App\Filament\Resources\SaleResource\Actions;

use Filament\Forms;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Percy\Core\Models\Sale;
use Percy\Core\Services\Cash\CashRegisterService;
use Percy\Core\Services\Inventory\InventoryService;
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
                ->modalHeading('Procesar pedido web')
                ->modalDescription('Confirma el pago y completa la venta. Al procesar el pedido se descontará el stock y se registrará el Kardex.')
                ->visible(fn (Sale $record) =>
                    $record->channel === 'ecommerce' &&
                    $record->status === 'pending_payment' &&
                    Auth::user()?->canProcessWebOrders() &&
                    app(CashRegisterService::class)->openCashRegisterForTenant($record->tenant_id)
                )
                ->using(function (Model $record, array $data): Model {
                    return DB::transaction(function () use ($record, $data): Model {
                        /** @var Sale $sale */
                        $sale = Sale::query()
                            ->where('tenant_id', $record->tenant_id)
                            ->whereKey($record->id)
                            ->lockForUpdate()
                            ->with('items')
                            ->firstOrFail();

                        if ($sale->status !== 'pending_payment' || $sale->channel !== 'ecommerce') {
                            throw ValidationException::withMessages([
                                'sale' => 'Solo se pueden procesar pedidos web pendientes.',
                            ]);
                        }

                        $cashRegister = app(CashRegisterService::class)
                            ->requireOpenCashRegisterForTenant($sale->tenant_id);

                        $data['status'] = 'completed';
                        $data['user_id'] = Auth::id();
                        $data['cash_register_id'] = $cashRegister->id;

                        $tipoDoc = $data['document_type'] ?? $sale->document_type;

                        if (in_array($tipoDoc, ['01', '03'], true)) {
                            $data['sunat_status'] = 'pending';
                        } else {
                            unset($data['sunat_status']);
                        }

                        $tipoNuevo = $data['document_type'] ?? $sale->document_type;
                        $serieNueva = $data['series'] ?? $sale->series;

                        if (
                            $sale->document_type !== $tipoNuevo ||
                            $sale->series !== $serieNueva ||
                            empty($sale->correlative)
                        ) {
                            $data['correlative'] = app(CorrelativeService::class)
                                ->next($sale->tenant_id, $tipoNuevo, $serieNueva);
                        }

                        $sale->forceFill($data);
                        $sale->save();

                        $sale->loadMissing('items');

                        foreach ($sale->items as $item) {
                            app(InventoryService::class)
                                ->deductStock($item, (float) $item->quantity);
                        }

                        return $sale->fresh([
                            'items',
                            'customer',
                            'user',
                            'cashRegister',
                        ]);
                    });
                })
                ->successNotificationTitle('Pedido procesado correctamente'),

            Tables\Actions\Action::make('cancel_web_order')
                ->label('Cancelar Pedido')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar pedido web')
                ->modalDescription('El pedido será marcado como cancelado. No se devolverá stock porque el pedido pendiente todavía no descuenta inventario.')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo de cancelación')
                        ->placeholder('Ej: Cliente no confirmó el pago, producto no disponible, pedido duplicado, etc.')
                        ->rows(3)
                        ->maxLength(255),
                ])
                ->visible(fn (Sale $record) =>
                    $record->channel === 'ecommerce' &&
                    $record->status === 'pending_payment' &&
                    Auth::user()?->canProcessWebOrders()
                )
                ->action(function (Sale $record, array $data): void {
                    DB::transaction(function () use ($record, $data): void {
                        /** @var Sale $sale */
                        $sale = Sale::query()
                            ->where('tenant_id', $record->tenant_id)
                            ->whereKey($record->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        if ($sale->status !== 'pending_payment' || $sale->channel !== 'ecommerce') {
                            throw ValidationException::withMessages([
                                'sale' => 'Solo se pueden cancelar pedidos web pendientes.',
                            ]);
                        }

                        $sale->update([
                            'status' => 'canceled',
                            'cancel_reason_description' => $data['reason'] ?: 'Pedido web cancelado.',
                        ]);
                    });
                })
                ->successNotificationTitle('Pedido cancelado correctamente'),
        ];
    }
}
