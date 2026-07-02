<?php

namespace App\Filament\Resources\ProductResource\Actions;

use Carbon\Carbon;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Percy\Core\Models\Product;
use Percy\Core\Models\ProductBatch;
use Percy\Core\Services\Inventory\InventoryService;
use Percy\Core\Services\Tenants\TenantFeatureService;

class ProductTableActions
{
    public static function actions(): array
    {
        return [
            Tables\Actions\ViewAction::make()
                ->label('Ver detalles')
                ->icon('heroicon-o-eye')
                ->color('info'),

            Tables\Actions\EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('warning'),

            Tables\Actions\DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Producto')
                ->modalDescription('¿Estás seguro de que deseas eliminar este producto? Esta acción no se puede deshacer.'),

            Tables\Actions\RestoreAction::make()
                ->label('Restaurar')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Restaurar Producto')
                ->modalDescription('¿Deseas rescatar este producto de la papelera? Volverá a estar visible y activo en el sistema.'),

            Tables\Actions\Action::make('manual_adjustment')
                ->label('Ajuste de Inventario')
                ->icon('heroicon-o-scale')
                ->color('warning')
                ->visible(fn(): bool => Auth::user()?->canManageStock() ?? false)
                ->form(fn(Product $record): array => self::manualAdjustmentForm($record))
                ->action(function (array $data, Product $record): void {
                    try {
                        app(InventoryService::class)->manualAdjustStock(
                            product: $record,
                            type: $data['type'],
                            quantity: abs((float) $data['quantity']),
                            productBatchId: $data['product_batch_id'] ?? null,
                            measurementUnit: $data['measurement_unit'] ?? null,
                            reason: $data['reason'] ?? null
                        );

                        Notification::make()
                            ->title('Inventario ajustado')
                            ->body('El ajuste fue registrado correctamente en los movimientos de inventario.')
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('No se pudo ajustar el inventario')
                            ->body(collect($e->errors())->flatten()->first() ?? 'Verifica el stock antes de continuar.')
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading(fn(Product $record): string => 'Ajuste de Inventario: ' . $record->name)
                ->modalDescription(function (Product $record): HtmlString {
                    return new HtmlString(
                        'Estás a punto de modificar el stock de <strong>' .
                            e($record->name) .
                            '</strong>. <br>Stock global actual: <strong>' .
                            self::formatStock($record->current_stock, $record) .
                            '</strong>'
                    );
                })
                ->modalWidth('lg'),
        ];
    }

    private static function manualAdjustmentForm(Product $record): array
    {
        $features = self::tenantFeatures();

        $hasLots = $features['has_lots'] ?? false;
        $hasExpiry = $features['has_expiry_dates'] ?? false;
        $usesBatches = $hasLots || $hasExpiry;

        return [
            Forms\Components\Select::make('type')
                ->label('Motivo del Ajuste')
                ->options([
                    'OUT' => 'Salida (Merma, Vencimiento, Rotura)',
                    'IN' => 'Ingreso (Inventario Inicial, Sobrante)',
                ])
                ->required()
                ->default('OUT')
                ->live(),

            Forms\Components\Select::make('product_batch_id')
                ->label($hasLots ? 'Lote a afectar' : 'Fecha de Vencimiento a afectar')
                ->options(function () use ($record, $hasLots): array {
                    return ProductBatch::query()
                        ->where('tenant_id', Auth::user()?->tenant_id)
                        ->where('product_id', $record->id)
                        ->where('is_active', true)
                        ->get()
                        ->mapWithKeys(function (ProductBatch $batch) use ($record, $hasLots): array {
                            $vence = $batch->expiration_date
                                ? Carbon::parse($batch->expiration_date)->format('d/m/Y')
                                : 'N/D';

                            $textoStock = self::formatStock($batch->current_quantity, $record);

                            $texto = $hasLots
                                ? "Lote: {$batch->batch_number} | Vence: {$vence} | Stock: {$textoStock}"
                                : "Vence: {$vence} | Stock Actual: {$textoStock}";

                            return [$batch->id => $texto];
                        })
                        ->toArray();
                })
                ->visible(fn(): bool => $usesBatches)
                ->required(fn(): bool => $usesBatches)
                ->searchable()
                ->preload(),

            Forms\Components\Select::make('measurement_unit')
                ->label('Unidad de Ajuste')
                ->options([
                    'box' => 'Caja Entera',
                    'unit' => 'Unidad Suelta (Pastilla/Blíster)',
                ])
                ->visible(fn(): bool => $hasLots && $record->is_fractionable && $record->units_per_box > 0)
                ->required(fn(): bool => $hasLots && $record->is_fractionable && $record->units_per_box > 0)
                ->default('box')
                ->live(),

            Forms\Components\TextInput::make('quantity')
                ->label('Cantidad')
                ->numeric()
                ->minValue(0.001)
                ->required()
                ->live(),

            Forms\Components\Placeholder::make('stock_warning')
                ->label('')
                ->content(function (Forms\Get $get) use ($hasLots, $record): string {
                    if ($get('type') !== 'OUT' || ! $get('quantity')) {
                        return '';
                    }

                    $stockActual = (float) $record->current_stock;
                    $cantidadIngresada = (float) $get('quantity');

                    $cantidadAjuste = $cantidadIngresada;

                    if (
                        $hasLots &&
                        $record->is_fractionable &&
                        $get('measurement_unit') === 'unit' &&
                        $record->units_per_box > 0
                    ) {
                        $cantidadAjuste = $cantidadIngresada / $record->units_per_box;
                    }

                    $stockFinal = $stockActual - $cantidadAjuste;
                    $textoActual = self::formatStock($stockActual, $record);

                    if ($stockFinal < 0) {
                        $textoFaltante = self::formatStock(abs($stockFinal), $record);

                        return "⚠️ Stock Global insuficiente. Actual: {$textoActual} | Faltarían: {$textoFaltante}";
                    }

                    $textoFinal = self::formatStock($stockFinal, $record);

                    return "✓ Stock Global actual: {$textoActual} | Quedará en: {$textoFinal}";
                })
                ->visible(fn(Forms\Get $get): bool => $get('type') === 'OUT'),

            Forms\Components\TextInput::make('reason')
                ->label('Detalle / Observación')
                ->required()
                ->maxLength(255)
                ->placeholder('Ej: 3 tomates podridos, 1 caja rota...'),
        ];
    }

    private static function tenantFeatures(): array
    {
        return app(TenantFeatureService::class)->features();
    }

    private static function formatStock(float|int|string|null $stockDecimal, Product $product): string
    {
        $stock = (float) $stockDecimal;

        if ($product->is_fractionable && $product->units_per_box > 0) {
            $cajas = floor(abs($stock));
            $fraccion = abs($stock) - $cajas;
            $unidades = round($fraccion * $product->units_per_box);

            $texto = [];

            if ($cajas > 0) {
                $texto[] = "{$cajas} caj";
            }

            if ($unidades > 0) {
                $texto[] = "{$unidades} und";
            }

            return empty($texto) ? '0 und' : implode(' y ', $texto);
        }

        if ($product->is_weighable) {
            $codigoUnidad = $product->unidadSunat ? $product->unidadSunat->codigo : '';

            $sufijo = match ($codigoUnidad) {
                'KGM' => 'kg',
                'LTR' => 'lt',
                'GLL' => 'gal',
                default => 'und',
            };

            return number_format($stock, 2) . " {$sufijo}";
        }

        return number_format($stock, 0) . ' und';
    }
}
