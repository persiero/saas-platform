<?php

namespace App\Filament\Resources\ProductResource\Tables;

use App\Filament\Resources\ProductResource\Actions\ProductTableActions;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Product;
use Percy\Core\Services\Tenants\TenantFeatureService;

class ProductTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['category', 'unidadSunat']))
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->columns(self::columns())
            ->defaultSort('name', 'asc')
            ->filters(self::filters())
            ->actions([
                Tables\Actions\ActionGroup::make(ProductTableActions::actions())
                    ->label('Acciones')
                    ->tooltip('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->color('gray'),
            ])
            ->bulkActions(self::bulkActions())
            ->emptyStateHeading('Sin productos registrados')
            ->emptyStateDescription('Comienza agregando tu primer producto o servicio')
            ->emptyStateIcon('heroicon-o-cube');
    }

    private static function columns(): array
    {
        return [
            Tables\Columns\TextColumn::make('mobile_summary')
                ->label('Producto')
                ->state(fn(Product $record): string => $record->name)
                ->description(function (Product $record): string {
                    $tipo = $record->type === 'service' ? 'Servicio' : 'Producto';
                    $categoria = $record->category?->name ?? 'Sin categoría';
                    $stock = self::formatStock($record->current_stock, $record);
                    $precio = 'S/ ' . number_format((float) $record->price, 2);

                    return "{$tipo} · {$categoria} · {$precio} · Stock: {$stock}";
                })
                ->icon(
                    fn(Product $record): string => $record->type === 'service'
                        ? 'heroicon-o-wrench-screwdriver'
                        : 'heroicon-o-cube'
                )
                ->color(fn(Product $record): string => $record->type === 'service' ? 'info' : 'gray')
                ->weight('black')
                ->wrap()
                ->searchable(['name', 'barcode'])
                ->hiddenFrom('md'),

            Tables\Columns\ImageColumn::make('image')
                ->label('Foto')
                ->disk('r2_public')
                ->square()
                ->size(40)
                ->toggleable()
                ->visibleFrom('md'),

            Tables\Columns\TextColumn::make('name')
                ->label('Producto')
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->icon('heroicon-o-cube')
                ->description(fn(Product $record): ?string => $record->category?->name)
                ->visibleFrom('md'),

            Tables\Columns\TextColumn::make('barcode')
                ->label('Cód. Barras')
                ->icon('heroicon-o-qr-code')
                ->searchable()
                ->hidden(function (): bool {
                    $features = self::tenantFeatures();

                    return ! ($features['has_barcode_scanner'] ?? true);
                })
                ->sortable()
                ->copyable()
                ->copyMessage('Código copiado')
                ->placeholder('Sin código')
                ->toggleable()
                ->visibleFrom('xl'),

            Tables\Columns\TextColumn::make('price')
                ->label('Precio')
                ->money('PEN')
                ->sortable()
                ->weight('black')
                ->color('primary')
                ->size('lg')
                ->description(fn(Product $record): string => $record->type === 'service' ? 'Servicio' : 'Producto')
                ->visibleFrom('md'),

            Tables\Columns\TextColumn::make('current_stock')
                ->label('Stock')
                ->badge()
                ->state(function (Product $record): float {
                    $record->refresh();

                    return (float) $record->current_stock;
                })
                ->formatStateUsing(fn(float $state, Product $record): string => self::formatStock($state, $record))
                ->icon(function (float $state, Product $record): ?string {
                    if ($record->type === 'service') {
                        return null;
                    }

                    return match (true) {
                        $state <= 5 => 'heroicon-o-exclamation-triangle',
                        $state <= 15 => 'heroicon-o-exclamation-circle',
                        default => 'heroicon-o-check-circle',
                    };
                })
                ->color(function (float $state, Product $record): string {
                    if ($record->type === 'service') {
                        return 'gray';
                    }

                    return match (true) {
                        $state <= 5 => 'danger',
                        $state <= 15 => 'warning',
                        default => 'success',
                    };
                })
                ->sortable()
                ->visibleFrom('md'),

            Tables\Columns\TextColumn::make('unidadSunat.codigo')
                ->label('Unidad')
                ->badge()
                ->color('gray')
                ->visibleFrom('xl'),

            Tables\Columns\ToggleColumn::make('active')
                ->label('Activo')
                ->sortable()
                ->disabled(fn(): bool => ! (Auth::user()?->canEditProducts() ?? false))
                ->visibleFrom('lg'),
        ];
    }

    private static function filters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('type')
                ->label('Tipo de ítem')
                ->options([
                    'product' => 'Productos físicos',
                    'service' => 'Servicios',
                ])
                ->native(false),

            Tables\Filters\SelectFilter::make('category_id')
                ->label('Categoría')
                ->relationship(
                    'category',
                    'name',
                    modifyQueryUsing: fn(Builder $query) => self::scopeToCurrentTenant($query)
                )
                ->multiple()
                ->preload(),

            Tables\Filters\TernaryFilter::make('active')
                ->label('Estado')
                ->placeholder('Todos')
                ->trueLabel('Solo activos')
                ->falseLabel('Solo inactivos')
                ->native(false),

            Tables\Filters\TernaryFilter::make('has_barcode')
                ->label('¿Tiene Cód. Barras?')
                ->placeholder('Todos los productos')
                ->trueLabel('Con código')
                ->falseLabel('Sin código')
                ->queries(
                    true: fn(Builder $query) => $query->whereNotNull('barcode'),
                    false: fn(Builder $query) => $query->whereNull('barcode'),
                ),

            Tables\Filters\SelectFilter::make('stock_status')
                ->label('Estado de stock')
                ->options([
                    'low' => 'Stock crítico',
                    'warning' => 'Stock bajo',
                    'available' => 'Con stock',
                    'service' => 'Servicios',
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return match ($data['value'] ?? null) {
                        'low' => $query->where('type', 'product')->where('current_stock', '<=', 5),
                        'warning' => $query->where('type', 'product')->whereBetween('current_stock', [6, 15]),
                        'available' => $query->where('type', 'product')->where('current_stock', '>', 15),
                        'service' => $query->where('type', 'service'),
                        default => $query,
                    };
                }),

            TrashedFilter::make(),
        ];
    }

    private static function bulkActions(): array
    {
        return [
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar seleccionados')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Productos')
                    ->modalDescription('¿Estás seguro de que deseas eliminar los productos seleccionados?'),

                Tables\Actions\RestoreBulkAction::make()
                    ->label('Restaurar seleccionados'),
            ]),
        ];
    }

    private static function tenantFeatures(): array
    {
        return app(TenantFeatureService::class)->features();
    }

    private static function scopeToCurrentTenant(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user?->isSuperAdmin()) {
            return $query;
        }

        return $query->where('tenant_id', $user?->tenant_id);
    }

    private static function formatStock(float|int|string|null $stockDecimal, Product $product): string
    {
        if ($product->type === 'service') {
            return '---';
        }

        $stock = (float) $stockDecimal;

        if ($product->is_fractionable && $product->units_per_box > 0) {
            $cajas = floor(abs($stock));
            $fraccion = abs($stock) - $cajas;
            $unidades = round($fraccion * $product->units_per_box);

            $texto = [];

            if ($cajas > 0) {
                $texto[] = "{$cajas} Cajas";
            }

            if ($unidades > 0) {
                $texto[] = "{$unidades} Und";
            }

            return empty($texto) ? '0 Und' : implode(' y ', $texto);
        }

        if ($product->is_weighable) {
            $codigoUnidad = $product->unidadSunat ? $product->unidadSunat->codigo : '';

            $sufijo = match ($codigoUnidad) {
                'KGM' => 'Kg',
                'LTR' => 'Lt',
                'GLL' => 'Gal',
                default => 'Und',
            };

            return number_format($stock, 2) . " {$sufijo}";
        }

        return number_format($stock, 0) . ' Und';
    }
}
