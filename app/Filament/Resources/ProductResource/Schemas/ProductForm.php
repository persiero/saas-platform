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
                    Forms\Components\Section::make('Información Principal')->schema([
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
                            ->maxLength(150),

                        Forms\Components\TextInput::make('barcode')
                            ->label('Código de Barras')
                            ->prefixIcon('heroicon-o-qr-code')
                            ->placeholder('Escanea o digita el código')
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule) => $rule->where('tenant_id', Auth::user()?->tenant_id)
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
                                fn (Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
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
                            ->default('product'),

                        Forms\Components\Select::make('category_id')
                            ->relationship(
                                'category',
                                'name',
                                modifyQueryUsing: fn (Builder $query) => self::scopeToCurrentTenant($query)
                            )
                            ->label('Categoría')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('description')
                            ->label('Descripción')
                            ->maxLength(255),
                    ])->columns(2),

                    Forms\Components\Section::make('Datos Farmacéuticos')
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
                                ->label('Requiere Receta Médica'),
                        ]),

                    Forms\Components\Section::make('Configuración de Venta Fraccionada')
                        ->description('Define si este producto se puede vender por blíster o por unidad suelta.')
                        ->visible(function (): bool {
                            $features = self::tenantFeatures();

                            return $features['has_lots'] ?? false;
                        })
                        ->schema([
                            Forms\Components\Toggle::make('is_fractionable')
                                ->label('¿Permitir venta por fracción (Pastillas/Blísteres)?')
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\Grid::make(3)
                                ->visible(fn (Forms\Get $get): bool => (bool) $get('is_fractionable'))
                                ->schema([
                                    Forms\Components\TextInput::make('units_per_box')
                                        ->label('Total de pastillas por Caja')
                                        ->numeric()
                                        ->required()
                                        ->helperText('Ej: 100'),

                                    Forms\Components\TextInput::make('units_per_blister')
                                        ->label('Pastillas por Blíster')
                                        ->numeric()
                                        ->helperText('Opcional. Ej: 10'),

                                    Forms\Components\TextInput::make('unit_price')
                                        ->label('Precio por Pastilla (Unidad)')
                                        ->numeric()
                                        ->prefix('S/')
                                        ->required()
                                        ->helperText('Precio de venta al menudeo.'),
                                ]),
                        ]),

                    Forms\Components\Section::make('Configuración de Venta a Granel (Peso / Volumen)')
                        ->description('Define si este producto se pesa en balanza o se mide en litros, en lugar de venderse por unidades enteras.')
                        ->icon('heroicon-o-scale')
                        ->visible(function (): bool {
                            $features = self::tenantFeatures();

                            return $features['sells_by_weight'] ?? false;
                        })
                        ->schema([
                            Forms\Components\Toggle::make('is_weighable')
                                ->label('¿Este producto se vende a granel (Kilos / Litros / Gramos)?')
                                ->helperText('Actívalo para verduras, frutas, carnes, pollo, aceite suelto, detergente líquido, etc.')
                                ->live()
                                ->columnSpanFull(),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Precios e Impuestos')->schema([
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
                            ->visible(fn (): bool => Auth::user()?->canViewProductCosts() ?? false),

                        Forms\Components\Select::make('afectacion_igv_id')
                            ->relationship('afectacionIgv', 'descripcion')
                            ->label('Afectación IGV')
                            ->required()
                            ->default(1)
                            ->searchable()
                            ->preload(),
                    ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
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
