<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use Percy\Core\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Percy\Core\Services\Tenants\TenantFeatureService;
use App\Filament\Resources\ProductResource\Tables\ProductTable;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $modelLabel = 'Producto';
    protected static ?string $pluralModelLabel = 'Productos';
    protected static ?int $navigationSort = 3;

    // Filtro global para asegurar que solo se vean productos del tenant actual
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->with(['category', 'unidadSunat'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        if (!$user?->isSuperAdmin()) {
            $query->where('tenant_id', $user?->tenant_id);
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->canViewProducts() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->canCreateProducts() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->canEditProducts() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->canDeleteProducts() ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return Auth::user()?->canRestoreProducts() ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()?->canDeleteProducts() ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return Auth::user()?->canRestoreProducts() ?? false;
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Información Principal')->schema([
                        // 🌟 AQUÍ COLOCAMOS EL SUBIDOR DE IMÁGENES
                        Forms\Components\FileUpload::make('image')
                            ->label('Imagen del Producto')
                            ->image()
                            ->disk('r2_public')
                            ->directory('productos')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->columnSpanFull(), // Ocupa todo el ancho

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
                                modifyRuleUsing: fn(Unique $rule) => $rule->where('tenant_id', Auth::user()?->tenant_id)
                            ) // Evita que dos productos tengan el mismo código
                            // 🌟 OCULTAR SEGÚN EL SEEDER
                            ->hidden(function () {
                                $features = self::tenantFeatures();
                                // Oculta el campo si 'has_barcode_scanner' es false. (Si no existe, asume true por defecto).
                                return !($features['has_barcode_scanner'] ?? true);
                            }),

                        Forms\Components\Select::make('unidad_sunat_id')
                            ->relationship('unidadSunat', 'descripcion')
                            ->label('Unidad de Medida (SUNAT)')
                            ->required()
                            ->default(1) // Por defecto NIU (Id 1)
                            ->searchable()
                            ->preload()
                            ->live() // 🌟 IMPORTANTE: Permite que el sistema sepa al instante qué unidad eligió
                            ->rules([ // 🌟 VALIDACIÓN DE DOBLE VÍA
                                fn(\Filament\Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $unidad = \Percy\Core\Models\UnidadSunat::find($value);
                                    if (!$unidad) return;

                                    $isWeighable = $get('is_weighable');
                                    // Definimos qué códigos consideramos "Granel"
                                    $isWeightOrVolume = in_array($unidad->codigo, ['KGM', 'LTR', 'GLL', 'GRM']);

                                    // Escenario 1: Encendió granel pero dejó Unidad
                                    if ($isWeighable && $unidad->codigo === 'NIU') {
                                        $fail('Si vendes a granel, debes elegir Kilos, Litros o similar.');
                                    }

                                    // Escenario 2: Eligió Kilos/Litros pero olvidó encender el interruptor
                                    if (!$isWeighable && $isWeightOrVolume) {
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
                                modifyQueryUsing: fn(Builder $query) => self::scopeToCurrentTenant($query)
                            ) // Solo mostrará categorías de ESTE tenant
                            ->label('Categoría')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('description')
                            ->label('Descripción')
                            ->maxLength(255),
                    ])->columns(2),

                    Forms\Components\Section::make('Datos Farmacéuticos')
                        // 🌟 MAGIA SAAS: Leemos la característica 'has_recipes' del JSON
                        ->visible(function () {
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
                        // 🌟 MAGIA SAAS: Vinculamos las fracciones a los negocios que manejan lotes
                        ->visible(function () {
                            $features = self::tenantFeatures();
                            return $features['has_lots'] ?? false;
                        })
                        ->schema([
                            Forms\Components\Toggle::make('is_fractionable')
                                ->label('¿Permitir venta por fracción (Pastillas/Blísteres)?')
                                ->live() // Esto es vital: Hace que la pantalla reaccione instantáneamente al hacer clic
                                ->columnSpanFull(),

                            // Este Grid SOLO aparece si el Toggle de arriba está encendido (true)
                            Forms\Components\Grid::make(3)
                                ->visible(fn(Forms\Get $get) => $get('is_fractionable'))
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

                    // =========================================================
                    // 🌟 SECCIÓN: CONFIGURACIÓN DE VENTA A GRANEL (Minimarkets)
                    // =========================================================
                    Forms\Components\Section::make('Configuración de Venta a Granel (Peso / Volumen)')
                        ->description('Define si este producto se pesa en balanza o se mide en litros, en lugar de venderse por unidades enteras.')
                        ->icon('heroicon-o-scale')
                        ->visible(function () {
                            $features = self::tenantFeatures();
                            return $features['sells_by_weight'] ?? false;
                        })
                        ->schema([
                            Forms\Components\Toggle::make('is_weighable')
                                ->label('¿Este producto se vende a granel (Kilos / Litros / Gramos)?')
                                ->helperText('Actívalo para verduras, frutas, carnes, pollo, aceite suelto, detergente líquido, etc.')
                                ->live()
                                // 🌟 ELIMINAMOS la autoselección. Ahora solo interactúa con la validación de arriba.
                                ->columnSpanFull(),
                        ]),

                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Precios e Impuestos')->schema([
                        Forms\Components\TextInput::make('price')
                            // 🌟 MAGIA SAAS: Label dinámico según el tipo de negocio
                            ->label(function () {
                                $features = self::tenantFeatures();
                                $isPharmacy = ($features['has_lots'] ?? false) && ($features['has_expiry_dates'] ?? false);

                                return $isPharmacy ? 'Precio de Venta (Caja)' : 'Precio de Venta';
                            })
                            ->numeric()
                            ->prefix('S/')
                            ->required(),

                        Forms\Components\TextInput::make('cost')
                            // 🌟 MAGIA SAAS: Label dinámico según el tipo de negocio
                            ->label(function () {
                                $features = self::tenantFeatures();
                                $isPharmacy = ($features['has_lots'] ?? false) && ($features['has_expiry_dates'] ?? false);

                                return $isPharmacy ? 'Costo Referencial (Caja)' : 'Costo Referencial';
                            })
                            ->numeric()
                            ->prefix('S/')
                            ->default(0)
                            ->visible(fn() => Auth::user()?->canViewProductCosts() ?? false),

                        Forms\Components\Select::make('afectacion_igv_id')
                            ->relationship('afectacionIgv', 'descripcion')
                            ->label('Afectación IGV')
                            ->required()
                            ->default(1) // Por defecto Gravado (Id 1)
                            ->searchable()
                            ->preload(),

                    ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3); // Divide la pantalla en 3 columnas para un diseño más pro
    }

    public static function table(Table $table): Table
    {
        return ProductTable::configure($table);
    }

    public static function getRelations(): array
    {
        $relations = [];

        // MAGIA DEL SAAS: Encendemos el módulo leyendo el JSON de características
        $features = self::tenantFeatures();

        $hasLots = $features['has_lots'] ?? false;
        $hasExpiry = $features['has_expiry_dates'] ?? false;

        // 🌟 EL CAMBIO CLAVE: Si el negocio usa lotes (Farmacia) O usa fechas de vencimiento (Minimarket)
        if ($hasLots || $hasExpiry) {
            $relations[] = RelationManagers\BatchesRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
