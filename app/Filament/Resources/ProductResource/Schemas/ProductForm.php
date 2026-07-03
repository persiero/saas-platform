<?php

namespace App\Filament\Resources\ProductResource\Schemas;

use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;
use Percy\Core\Models\UnidadSunat;
use Percy\Core\Services\Tenants\TenantFeatureService;

class ProductForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Información Principal')
                        ->icon('heroicon-o-cube')
                        ->description('Datos básicos del producto o servicio.')
                        ->schema([
                            Forms\Components\FileUpload::make('image')
                                ->label('Imagen del Producto')
                                ->image()
                                ->disk('r2_public')
                                ->directory('productos')
                                ->visibility('public')
                                ->imageEditor()
                                ->maxSize(2048)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('name')
                                ->label('Nombre del Producto / Servicio')
                                ->required()
                                ->maxLength(150)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('barcode')
                                ->label('Código de Barras')
                                ->prefixIcon('heroicon-o-qr-code')
                                ->placeholder('Escanea o digita el código')
                                ->unique(
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn(Unique $rule) => $rule->where('tenant_id', Auth::user()?->tenant_id)
                                )
                                ->hidden(function (): bool {
                                    $features = self::tenantFeatures();

                                    return ! ($features['has_barcode_scanner'] ?? true);
                                }),

                            Forms\Components\Select::make('unidad_sunat_id')
                                ->relationship('unidadSunat', 'descripcion')
                                ->label('Unidad de Medida (SUNAT)')
                                ->required()
                                ->default(1)
                                ->searchable()
                                ->preload()
                                ->live()
                                ->rules([
                                    fn(Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $unidad = UnidadSunat::find($value);

                                        if (! $unidad) {
                                            return;
                                        }

                                        $isWeighable = $get('is_weighable');
                                        $isWeightOrVolume = in_array($unidad->codigo, ['KGM', 'LTR', 'GLL', 'GRM']);

                                        if ($isWeighable && $unidad->codigo === 'NIU') {
                                            $fail('Si vendes a granel, debes elegir Kilos, Litros o similar.');
                                        }

                                        if (! $isWeighable && $isWeightOrVolume) {
                                            $fail('Para vender por peso o volumen, debes encender el interruptor de "Venta a Granel" abajo.');
                                        }
                                    },
                                ]),

                            Forms\Components\Select::make('type')
                                ->label('Tipo de Ítem')
                                ->options([
                                    'product' => 'Producto Físico',
                                    'service' => 'Servicio',
                                ])
                                ->required()
                                ->default('product')
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                    if ($state === 'service') {
                                        $set('is_fractionable', false);
                                        $set('is_weighable', false);
                                        $set('units_per_box', null);
                                        $set('units_per_blister', null);
                                        $set('unit_price', null);
                                    }
                                }),

                            Forms\Components\Select::make('category_id')
                                ->relationship(
                                    'category',
                                    'name',
                                    modifyQueryUsing: fn(Builder $query) => self::scopeToCurrentTenant($query)
                                )
                                ->label('Categoría')
                                ->searchable()
                                ->preload(),

                            Forms\Components\TextInput::make('description')
                                ->label('Descripción')
                                ->maxLength(255)
                                ->columnSpanFull(),

                        ])->columns([
                            'default' => 1,
                            'md' => 2,
                        ]),

                    Forms\Components\Section::make('Datos Farmacéuticos')
                        ->icon('heroicon-o-beaker')
                        ->description('Información adicional para productos que requieren control farmacéutico.')
                        ->visible(function (): bool {
                            $features = self::tenantFeatures();

                            return $features['has_recipes'] ?? false;
                        })
                        ->schema([
                            Forms\Components\TextInput::make('active_ingredient')
                                ->label('Principio Activo (Genérico)'),

                            Forms\Components\TextInput::make('laboratory')
                                ->label('Laboratorio'),

                            Forms\Components\Toggle::make('requires_prescription')
                                ->label('Requiere Receta Médica')
                                ->columnSpanFull(),
                        ])
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->collapsible(),

                    Forms\Components\Section::make('Configuración de Venta Fraccionada')
                        ->icon('heroicon-o-squares-plus')
                        ->description('Usa esta configuración cuando el producto pueda venderse por caja o por unidad suelta.')
                        ->visible(function (Forms\Get $get): bool {
                            $features = self::tenantFeatures();

                            return ($features['has_lots'] ?? false)
                                && $get('type') === 'product';
                        })
                        ->schema([
                            Forms\Components\Toggle::make('is_fractionable')
                                ->label('Permitir venta por fracción')
                                ->hintIcon(
                                    'heroicon-m-question-mark-circle',
                                    tooltip: 'Actívalo para productos que se venden por caja y también por unidad suelta, como pastillas o blísteres.'
                                )
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\Grid::make([
                                'default' => 1,
                                'md' => 3,
                            ])
                                ->visible(fn(Forms\Get $get): bool => (bool) $get('is_fractionable'))
                                ->schema([
                                    Forms\Components\TextInput::make('units_per_box')
                                        ->label('Unidades por caja')
                                        ->numeric()
                                        ->required()
                                        ->hint('Ej: 100'),

                                    Forms\Components\TextInput::make('units_per_blister')
                                        ->label('Unidades por blíster')
                                        ->numeric()
                                        ->hint('Opcional'),

                                    Forms\Components\TextInput::make('unit_price')
                                        ->label('Precio por unidad')
                                        ->numeric()
                                        ->prefix('S/')
                                        ->required()
                                        ->hintIcon(
                                            'heroicon-m-question-mark-circle',
                                            tooltip: 'Precio de venta al menudeo o unidad suelta.'
                                        ),
                                ]),
                        ])
                        ->collapsible(),

                    Forms\Components\Section::make('Configuración de Venta a Granel')
                        ->description('Para productos que se venden por kilos, litros, gramos u otra unidad medible.')
                        ->icon('heroicon-o-scale')
                        ->visible(function (Forms\Get $get): bool {
                            $features = self::tenantFeatures();

                            return ($features['sells_by_weight'] ?? false)
                                && $get('type') === 'product';
                        })
                        ->schema([
                            Forms\Components\Toggle::make('is_weighable')
                                ->label('Producto vendido a granel')
                                ->hintIcon(
                                    'heroicon-m-question-mark-circle',
                                    tooltip: 'Actívalo para verduras, frutas, carnes, pollo, aceite suelto, detergente líquido u otros productos medidos por peso o volumen.'
                                )
                                ->live()
                                ->columnSpanFull(),
                        ])
                        ->collapsible(),

                ])->columnSpan([
                    'default' => 1,
                    'xl' => 2,
                ]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Precios e Impuestos')
                        ->icon('heroicon-o-banknotes')
                        ->description('Define el precio de venta, costo referencial e impuesto.')
                        ->extraAttributes(['class' => 'xl:sticky xl:top-20'])
                        ->schema([
                            Forms\Components\TextInput::make('price')
                                ->label(function (): string {
                                    $features = self::tenantFeatures();
                                    $isPharmacy = ($features['has_lots'] ?? false) && ($features['has_expiry_dates'] ?? false);

                                    return $isPharmacy ? 'Precio de Venta (Caja)' : 'Precio de Venta';
                                })
                                ->numeric()
                                ->prefix('S/')
                                ->required(),

                            Forms\Components\TextInput::make('cost')
                                ->label(function (): string {
                                    $features = self::tenantFeatures();
                                    $isPharmacy = ($features['has_lots'] ?? false) && ($features['has_expiry_dates'] ?? false);

                                    return $isPharmacy ? 'Costo Referencial (Caja)' : 'Costo Referencial';
                                })
                                ->numeric()
                                ->prefix('S/')
                                ->default(0)
                                ->visible(fn(): bool => Auth::user()?->canViewProductCosts() ?? false),

                            Forms\Components\Select::make('afectacion_igv_id')
                                ->relationship('afectacionIgv', 'descripcion')
                                ->label('Afectación IGV')
                                ->required()
                                ->default(1)
                                ->searchable()
                                ->preload()
                                ->native(false),
                        ])
                        ->columns([
                            'default' => 1,
                        ]),

                ])->columnSpan([
                    'default' => 1,
                    'xl' => 1,
                ]),
            ])
            ->columns([
                'default' => 1,
                'xl' => 3,
            ]);
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
}
