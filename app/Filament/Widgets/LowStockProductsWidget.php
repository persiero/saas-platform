<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Product;

class LowStockProductsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->tenant || ! $user->tenant->businessSector) {
            return false;
        }

        $features = $user->tenant->businessSector->features ?? [];

        return (bool) ($features['has_expiry_dates'] ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->with(['category', 'unidadSunat'])
                    ->where('tenant_id', Auth::user()->tenant_id)
                    ->where('active', true)
                    ->where('type', 'product')
                    ->where('current_stock', '<=', 5)
                    ->orderBy('current_stock', 'asc')
            )
            ->heading('⚠️ Productos con Stock Bajo')
            ->description('Productos que necesitan reposición o revisión de inventario.')
            ->emptyStateHeading('Inventario saludable')
            ->emptyStateDescription('No hay productos con stock bajo.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn(Product $record): ?string => $record->category?->name),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock actual')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn($state, Product $record): string => self::formatStock($state, $record)),

                Tables\Columns\TextColumn::make('price')
                    ->label('Precio venta')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->since()
                    ->sortable(),
            ])
            ->paginated([5])
            ->striped();
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
