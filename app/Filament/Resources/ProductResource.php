<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use Percy\Core\Models\Product;
use Percy\Core\Models\InventoryMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Percy\Core\Services\Tenants\TenantFeatureService;
use Illuminate\Validation\ValidationException;
use Percy\Core\Services\Inventory\InventoryService;

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
        return $table
            ->modifyQueryUsing(fn(\Illuminate\Database\Eloquent\Builder $query) => $query->with('category'))
            ->columns([
                // 🌟 AQUÍ COLOCAMOS LA MINIATURA
                Tables\Columns\ImageColumn::make('image')
                    ->label('Foto')
                    ->disk('r2_public')
                    ->square()
                    ->size(40) // Tamaño sutil para la tabla
                    ->toggleable(), // Permite al usuario ocultarla si quiere más espacio

                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-cube')
                    ->description(fn(Product $record): ?string => $record->category?->name),

                Tables\Columns\TextColumn::make('barcode')
                    ->label('Cód. Barras')
                    ->icon('heroicon-o-qr-code')
                    ->searchable() // ¡Esto automáticamente agrega la búsqueda general por código!
                    // 🌟 OCULTAR LA COLUMNA SEGÚN EL SEEDER DEL NEGOCIO
                    ->hidden(function () {
                        $features = self::tenantFeatures();
                        return !($features['has_barcode_scanner'] ?? true);
                    })
                    ->sortable()
                    ->copyable() // UX Extra: Permite copiar el código con un clic
                    ->copyMessage('Código copiado')
                    ->placeholder('Sin código')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('PEN')
                    ->sortable()
                    ->weight('black') // Más grueso para que resalte
                    ->color('primary') // Le da el color de tu marca (Azul/Verde/Naranja)
                    ->size('lg'),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock')
                    ->badge()
                    ->state(function (Product $record): float {
                        $record->refresh();
                        return (float) $record->current_stock;
                    })
                    // 🌟 MAGIA UX: TRADUCTOR A HUMANO
                    ->formatStateUsing(function (float $state, \Percy\Core\Models\Product $record): string {
                        if ($record->type === 'service') {
                            return '---';
                        }

                        // 1. Fraccionable (Farmacia)
                        if ($record->is_fractionable && $record->units_per_box > 0) {
                            $cajas = floor(abs($state));
                            $fraccion = abs($state) - $cajas;
                            $unidades = round($fraccion * $record->units_per_box);

                            $texto = [];
                            if ($cajas > 0) $texto[] = "{$cajas} Cajas";
                            if ($unidades > 0) $texto[] = "{$unidades} Und";

                            return empty($texto) ? '0 Und' : implode(' y ', $texto);
                        }

                        // 2. Granel (Peso/Volumen)
                        if ($record->is_weighable) {
                            $codigoUnidad = $record->unidadSunat ? $record->unidadSunat->codigo : '';
                            $sufijo = match ($codigoUnidad) {
                                'KGM' => 'Kg',
                                'LTR' => 'Lt',
                                'GLL' => 'Gal',
                                default => 'Und'
                            };
                            return number_format($state, 2) . " {$sufijo}";
                        }

                        // 3. Normal (Enteros)
                        return number_format($state, 0) . ' Und';
                    })
                    // 🌟 TUS ÍCONOS ORIGINALES
                    ->icon(function (float $state, \Percy\Core\Models\Product $record): ?string {
                        if ($record->type === 'service') return null;
                        return match (true) {
                            $state <= 5 => 'heroicon-o-exclamation-triangle',
                            $state <= 15 => 'heroicon-o-exclamation-circle',
                            default => 'heroicon-o-check-circle',
                        };
                    })
                    // 🌟 TUS COLORES ORIGINALES
                    ->color(function (float $state, \Percy\Core\Models\Product $record): string {
                        if ($record->type === 'service') return 'gray';
                        return match (true) {
                            $state <= 5 => 'danger',
                            $state <= 15 => 'warning',
                            default => 'success',
                        };
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('unidadSunat.codigo')
                    ->label('Unidad')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\ToggleColumn::make('active')
                    ->label('Activo')
                    ->sortable()
                    ->disabled(fn() => !(Auth::user()?->canEditProducts() ?? false)),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
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

                Tables\Filters\Filter::make('low_stock')
                    ->label('Stock bajo')
                    ->query(fn(Builder $query): Builder => $query->whereHas('inventoryMovements', function ($q) {
                        // Filtro personalizado para stock bajo
                    }))
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    // 1. NUEVO BOTÓN: Para que el Cajero pueda consultar el producto sin modificarlo
                    Tables\Actions\ViewAction::make()
                        ->label('Ver detalles')
                        ->icon('heroicon-o-eye')
                        ->color('info'),

                    // 2. BOTÓN EDITAR (Filament lo oculta solo gracias a canEdit)
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil')
                        ->color('warning'),

                    Tables\Actions\DeleteAction::make() // Borrado lógico
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Producto')
                        ->modalDescription('¿Estás seguro de que deseas eliminar este producto? Esta acción no se puede deshacer.'),

                    Tables\Actions\RestoreAction::make()
                        ->label('Restaurar')
                        ->icon('heroicon-o-arrow-uturn-left') // Icono de "Deshacer"
                        ->color('success') // Color verde positivo
                        ->requiresConfirmation()
                        ->modalHeading('Restaurar Producto')
                        ->modalDescription('¿Deseas rescatar este producto de la papelera? Volverá a estar visible y activo en el sistema.'),

                    // 3. BOTÓN AJUSTE DE INVENTARIO: Protegido solo para el Admin
                    Tables\Actions\Action::make('manual_adjustment')
                        ->label('Ajuste de Inventario')
                        ->icon('heroicon-o-scale')
                        ->color('warning')
                        ->visible(fn() => Auth::user()?->canManageStock() ?? false)
                        ->form(function ($record) {
                            $features = self::tenantFeatures();
                            $hasLots = $features['has_lots'] ?? false;
                            $hasExpiry = $features['has_expiry_dates'] ?? false;
                            $usesBatches = $hasLots || $hasExpiry;

                            // 🌟 MAGIA UX: HELPER DE TRADUCCIÓN INTERNO
                            $traducirStock = function ($stockDecimal) use ($record) {
                                $stock = (float) $stockDecimal;

                                if ($record->is_fractionable && $record->units_per_box > 0) {
                                    $cajas = floor(abs($stock));
                                    $fraccion = abs($stock) - $cajas;
                                    $unidades = round($fraccion * $record->units_per_box);
                                    $texto = [];
                                    if ($cajas > 0) $texto[] = "{$cajas} caj";
                                    if ($unidades > 0) $texto[] = "{$unidades} und";
                                    return empty($texto) ? '0 und' : implode(' y ', $texto);
                                }

                                if ($record->is_weighable) {
                                    $codigoUnidad = $record->unidadSunat ? $record->unidadSunat->codigo : '';
                                    $sufijo = match ($codigoUnidad) {
                                        'KGM' => 'kg',
                                        'LTR' => 'lt',
                                        'GLL' => 'gal',
                                        default => 'und'
                                    };
                                    return number_format($stock, 2) . " {$sufijo}";
                                }

                                return number_format($stock, 0) . ' und';
                            };

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
                                    ->options(function () use ($record, $hasLots, $traducirStock) {
                                        return \Percy\Core\Models\ProductBatch::query()
                                            ->where('tenant_id', Auth::user()?->tenant_id)
                                            ->where('product_id', $record->id)
                                            ->where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(function ($b) use ($hasLots, $traducirStock) {
                                                $vence = $b->expiration_date ? \Carbon\Carbon::parse($b->expiration_date)->format('d/m/Y') : 'N/D';

                                                // 🌟 TRADUCIMOS EL STOCK DEL LOTE
                                                $textoStock = $traducirStock($b->current_quantity);

                                                $texto = $hasLots
                                                    ? "Lote: {$b->batch_number} | Vence: {$vence} | Stock: {$textoStock}"
                                                    : "Vence: {$vence} | Stock Actual: {$textoStock}";

                                                return [$b->id => $texto];
                                            });
                                    })
                                    ->visible(fn() => $usesBatches)
                                    ->required(fn() => $usesBatches)
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Select::make('measurement_unit')
                                    ->label('Unidad de Ajuste')
                                    ->options([
                                        'box' => 'Caja Entera',
                                        'unit' => 'Unidad Suelta (Pastilla/Blíster)',
                                    ])
                                    ->visible(fn() => $hasLots && $record->is_fractionable && $record->units_per_box > 0)
                                    ->required(fn() => $hasLots && $record->is_fractionable && $record->units_per_box > 0)
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
                                    ->content(function (Forms\Get $get) use ($hasLots, $record, $traducirStock) {
                                        if ($get('type') === 'OUT' && $get('quantity')) {
                                            $stockActual = (float) $record->current_stock;
                                            $cantidadIngresada = (float) $get('quantity');

                                            $cantidadAjuste = $cantidadIngresada;
                                            if ($hasLots && $record->is_fractionable && $get('measurement_unit') === 'unit' && $record->units_per_box > 0) {
                                                $cantidadAjuste = $cantidadIngresada / $record->units_per_box;
                                            }

                                            $stockFinal = $stockActual - $cantidadAjuste;

                                            // 🌟 TRADUCIMOS EL TEXTO DE LA ALERTA
                                            $textoActual = $traducirStock($stockActual);

                                            if ($stockFinal < 0) {
                                                $textoFaltante = $traducirStock(abs($stockFinal));
                                                return "⚠️ Stock Global insuficiente. Actual: {$textoActual} | Faltarían: {$textoFaltante}";
                                            }

                                            $textoFinal = $traducirStock($stockFinal);
                                            return "✓ Stock Global actual: {$textoActual} | Quedará en: {$textoFinal}";
                                        }
                                        return '';
                                    })
                                    ->visible(fn(Forms\Get $get) => $get('type') === 'OUT'),

                                Forms\Components\TextInput::make('reason')
                                    ->label('Detalle / Observación')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ej: 3 tomates podridos, 1 caja rota...'),
                            ];
                        })
                        ->action(function (array $data, Product $record) {
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
                                    ->body('El ajuste fue registrado correctamente en el Kardex.')
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
                        ->modalHeading(fn($record) => 'Ajuste de Inventario: ' . $record->name)
                        ->modalDescription(function ($record) {
                            // 🌟 TRADUCIMOS LA DESCRIPCIÓN DEL MODAL
                            $stock = (float) $record->current_stock;
                            $textoStock = '';

                            if ($record->is_fractionable && $record->units_per_box > 0) {
                                $cajas = floor(abs($stock));
                                $fraccion = abs($stock) - $cajas;
                                $unidades = round($fraccion * $record->units_per_box);
                                $t = [];
                                if ($cajas > 0) $t[] = "{$cajas} caj";
                                if ($unidades > 0) $t[] = "{$unidades} und";
                                $textoStock = empty($t) ? '0 und' : implode(' y ', $t);
                            } elseif ($record->is_weighable) {
                                $cu = $record->unidadSunat ? $record->unidadSunat->codigo : '';
                                $su = match ($cu) {
                                    'KGM' => 'kg',
                                    'LTR' => 'lt',
                                    'GLL' => 'gal',
                                    default => 'und'
                                };
                                $textoStock = number_format($stock, 2) . " {$su}";
                            } else {
                                $textoStock = number_format($stock, 0) . ' und';
                            }

                            return new \Illuminate\Support\HtmlString(
                                'Estás a punto de modificar el stock de <strong>' . $record->name . '</strong>. <br>Stock global actual: <strong>' . $textoStock . '</strong>'
                            );
                        })
                        ->modalWidth('lg'),
                ])
                    ->label('Acciones')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Productos')
                        ->modalDescription('¿Estás seguro de que deseas eliminar los productos seleccionados?'),
                    Tables\Actions\RestoreBulkAction::make(), // 🌟 Restaurar varios a la vez
                ]),
            ])
            ->emptyStateHeading('Sin productos registrados')
            ->emptyStateDescription('Comienza agregando tu primer producto o servicio')
            ->emptyStateIcon('heroicon-o-cube');
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
