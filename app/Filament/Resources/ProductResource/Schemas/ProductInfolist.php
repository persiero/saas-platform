<?php

namespace App\Filament\Resources\ProductResource\Schemas;

use Filament\Infolists\Infolist;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Product;
use Percy\Core\Services\Tenants\TenantFeatureService;

class ProductInfolist
{
    public static function configure(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Resumen del Producto')
                    ->icon('heroicon-o-cube')
                    ->description('Información principal del producto o servicio.')
                    ->schema([
                        ImageEntry::make('image')
                            ->label('Imagen')
                            ->disk('r2_public')
                            ->height(120)
                            ->square()
                            ->placeholder('Sin imagen')
                            ->columnSpan([
                                'default' => 1,
                                'md' => 1,
                            ]),

                        TextEntry::make('name')
                            ->label('Nombre')
                            ->weight('black')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->icon('heroicon-o-cube')
                            ->columnSpan([
                                'default' => 1,
                                'md' => 2,
                            ]),

                        TextEntry::make('type')
                            ->label('Tipo')
                            ->formatStateUsing(fn(?string $state): string => $state === 'service' ? 'Servicio' : 'Producto físico')
                            ->badge()
                            ->color(fn(?string $state): string => $state === 'service' ? 'info' : 'success'),

                        TextEntry::make('active')
                            ->label('Estado')
                            ->formatStateUsing(fn(bool $state): string => $state ? 'Activo' : 'Inactivo')
                            ->badge()
                            ->color(fn(bool $state): string => $state ? 'success' : 'danger'),

                        TextEntry::make('category.name')
                            ->label('Categoría')
                            ->placeholder('Sin categoría')
                            ->icon('heroicon-o-tag'),

                        TextEntry::make('barcode')
                            ->label('Código de barras')
                            ->placeholder('Sin código')
                            ->copyable()
                            ->copyMessage('Código copiado')
                            ->icon('heroicon-o-qr-code')
                            ->visible(fn(Product $record): bool => filled($record->barcode)),

                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('Sin descripción')
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ]),

                Section::make('Precios e Impuestos')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextEntry::make('price')
                            ->label(
                                fn(Product $record): string => self::isPharmacy()
                                    ? 'Precio de Venta (Caja)'
                                    : 'Precio de Venta'
                            )
                            ->money('PEN')
                            ->weight('black')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->color('success'),

                        TextEntry::make('cost')
                            ->label(
                                fn(Product $record): string => self::isPharmacy()
                                    ? 'Costo Referencial (Caja)'
                                    : 'Costo Referencial'
                            )
                            ->money('PEN')
                            ->visible(fn(): bool => Auth::user()?->canViewProductCosts() ?? false),

                        TextEntry::make('afectacionIgv.descripcion')
                            ->label('Afectación IGV')
                            ->badge()
                            ->color('gray')
                            ->placeholder('No configurado'),

                        TextEntry::make('unidadSunat.descripcion')
                            ->label('Unidad de Medida')
                            ->placeholder('No configurada'),

                        TextEntry::make('unidadSunat.codigo')
                            ->label('Código SUNAT')
                            ->badge()
                            ->color('gray')
                            ->placeholder('No configurado'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 5,
                    ]),

                Section::make('Stock y Configuración de Venta')
                    ->icon('heroicon-o-archive-box')
                    ->visible(fn(Product $record): bool => $record->type === 'product')
                    ->schema([
                        TextEntry::make('current_stock')
                            ->label('Stock actual')
                            ->state(fn(Product $record): string => self::formatStock($record->current_stock, $record))
                            ->badge()
                            ->color(fn(Product $record): string => match (true) {
                                (float) $record->current_stock <= 5 => 'danger',
                                (float) $record->current_stock <= 15 => 'warning',
                                default => 'success',
                            }),

                        TextEntry::make('is_fractionable')
                            ->label('Venta fraccionada')
                            ->formatStateUsing(fn(bool $state): string => $state ? 'Permitida' : 'No aplica')
                            ->badge()
                            ->color(fn(bool $state): string => $state ? 'info' : 'gray')
                            ->visible(fn(): bool => self::hasLots()),

                        TextEntry::make('units_per_box')
                            ->label('Unidades por caja')
                            ->suffix(' und')
                            ->visible(fn(Product $record): bool => self::hasLots() && (bool) $record->is_fractionable),

                        TextEntry::make('units_per_blister')
                            ->label('Unidades por blíster')
                            ->suffix(' und')
                            ->placeholder('No configurado')
                            ->visible(fn(Product $record): bool => self::hasLots() && (bool) $record->is_fractionable),

                        TextEntry::make('unit_price')
                            ->label('Precio por unidad')
                            ->money('PEN')
                            ->visible(fn(Product $record): bool => self::hasLots() && (bool) $record->is_fractionable),

                        TextEntry::make('is_weighable')
                            ->label('Venta a granel')
                            ->formatStateUsing(fn(bool $state): string => $state ? 'Sí' : 'No')
                            ->badge()
                            ->color(fn(bool $state): string => $state ? 'info' : 'gray')
                            ->visible(fn(): bool => self::sellsByWeight()),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 4,
                    ]),

                Section::make('Datos Farmacéuticos')
                    ->icon('heroicon-o-beaker')
                    ->visible(fn(Product $record): bool => self::hasRecipes() && $record->type === 'product')
                    ->schema([
                        TextEntry::make('active_ingredient')
                            ->label('Principio activo')
                            ->placeholder('No registrado'),

                        TextEntry::make('laboratory')
                            ->label('Laboratorio')
                            ->placeholder('No registrado'),

                        TextEntry::make('requires_prescription')
                            ->label('Receta médica')
                            ->formatStateUsing(fn(bool $state): string => $state ? 'Requiere receta' : 'No requiere receta')
                            ->badge()
                            ->color(fn(bool $state): string => $state ? 'warning' : 'success'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->collapsible(),
            ]);
    }

    private static function tenantFeatures(): array
    {
        return app(TenantFeatureService::class)->features();
    }

    private static function hasLots(): bool
    {
        $features = self::tenantFeatures();

        return $features['has_lots'] ?? false;
    }

    private static function hasRecipes(): bool
    {
        $features = self::tenantFeatures();

        return $features['has_recipes'] ?? false;
    }

    private static function sellsByWeight(): bool
    {
        $features = self::tenantFeatures();

        return $features['sells_by_weight'] ?? false;
    }

    private static function isPharmacy(): bool
    {
        $features = self::tenantFeatures();

        return ($features['has_lots'] ?? false) && ($features['has_expiry_dates'] ?? false);
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
