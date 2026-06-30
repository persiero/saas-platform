<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use Percy\Core\Models\Purchase;
use Percy\Core\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Percy\Core\Models\Supplier;
use Percy\Core\Services\Tenants\TenantFeatureService;
use Percy\Core\Services\Tenants\TenantPricingService;
use Filament\Notifications\Notification;
use Percy\Core\Models\ProductBatch;
use Illuminate\Support\Facades\DB;
use Percy\Core\Services\Inventory\InventoryService;
use Illuminate\Validation\ValidationException;
use Percy\Core\Services\Purchases\PurchaseService;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?string $modelLabel = 'Compra';
    protected static ?string $pluralModelLabel = 'Compras';
    protected static ?int $navigationSort = 1;

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

    public static function canViewAny(): bool
    {
        return Auth::user()?->canViewPurchases() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->canCreatePurchases() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        if (! Auth::user()?->canEditPurchases()) {
            return false;
        }

        if ($record instanceof Purchase && $record->status !== 'pending') {
            return false;
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        if (! Auth::user()?->canDeletePurchases()) {
            return false;
        }

        if ($record instanceof Purchase && $record->status !== 'pending') {
            return false;
        }

        return true;
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()?->canDeletePurchases() ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['supplier']);

        return self::scopeToCurrentTenant($query);
    }

    private static function normalizeDateForComparison(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return \Carbon\Carbon::instance($value)->format('Y-m-d');
            }

            $value = trim((string) $value);

            // Formato normal del DatePicker: 2026-11-30
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
            }

            // Formato que está llegando desde Livewire: 2026-11-30 00:00:00
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $value)) {
                return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $value)->format('Y-m-d');
            }

            // Formato visible: 30/11/2026
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function batchExpirationMismatchMessage(Get $get, mixed $selectedValue = null): ?string
    {
        $productId = $get('product_id');
        $batchNumber = strtoupper(trim((string) $get('batch_number')));
        $expirationValue = $selectedValue ?? $get('expiration_date');

        if (!$productId || blank($batchNumber) || blank($expirationValue)) {
            return null;
        }

        $batch = ProductBatch::query()
            ->where('tenant_id', Auth::user()?->tenant_id)
            ->where('product_id', $productId)
            ->where('batch_number', $batchNumber)
            ->first();

        if (!$batch || !$batch->expiration_date) {
            return null;
        }

        $existing = self::normalizeDateForComparison($batch->expiration_date);
        $selected = self::normalizeDateForComparison($expirationValue);

        if (!$existing || !$selected || $existing === $selected) {
            return null;
        }

        return 'Este lote ya existe con vencimiento ' .
            $batch->expiration_date->format('d/m/Y') .
            '. Verifica el número de lote o la fecha de vencimiento.';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // =========================================================
                // 🌟 CABECERA (Top): Proveedor, Documento y Resumen
                // =========================================================
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\Group::make()->schema([
                        Forms\Components\Section::make('Datos del Proveedor')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Forms\Components\Select::make('supplier_id')
                                    ->relationship(
                                        'supplier',
                                        'name',
                                        modifyQueryUsing: fn(Builder $query) => self::scopeToCurrentTenant($query)
                                            ->where('active', true)
                                    )
                                    ->label('Proveedor')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->helperText('Selecciona el proveedor de esta compra')
                                    // 🌟 AQUÍ ESTÁ EL TRUCO PARA EL BOTÓN BONITO
                                    ->manageOptionActions(function (\Filament\Forms\Components\Actions\Action $action) {
                                        return $action
                                            ->icon('heroicon-o-user-plus') // Un icono de "Agregar Usuario/Empresa"
                                            ->color('info') // Le da color verde para que resalte
                                            ->tooltip('Agregar Nuevo Proveedor'); // Texto al pasar el mouse
                                    })
                                    ->createOptionModalHeading('Registrar Nuevo Proveedor')
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nombre / Razón Social')
                                            ->required(),
                                        Forms\Components\TextInput::make('ruc')
                                            ->label('RUC')
                                            ->length(11)
                                            ->numeric(), // Siempre es bueno forzar a que sean solo números
                                        Forms\Components\Toggle::make('active')
                                            ->label('Activo')
                                            ->default(true),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        $data['tenant_id'] = Auth::user()?->tenant_id;

                                        return Supplier::create($data)->getKey();
                                    })
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Section::make('Detalles del Documento')
                            ->icon('heroicon-o-document-text')
                            ->columns(3)
                            ->schema([
                                Forms\Components\TextInput::make('document_number')
                                    ->label('N° de Documento')
                                    ->placeholder('Ej: F001-00123')
                                    ->prefixIcon('heroicon-o-hashtag')
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('purchase_date')
                                    ->label('Fecha de Compra')
                                    ->default(now())
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now())
                                    ->prefixIcon('heroicon-o-calendar')
                                    ->columnSpan(1),

                                Forms\Components\Select::make('status')
                                    ->label('Estado')
                                    ->options(
                                        fn(?Purchase $record): array => $record
                                            ? [
                                                'pending' => 'Pendiente',
                                                'completed' => 'Completado',
                                                'canceled' => 'Cancelado',
                                            ]
                                            : [
                                                'pending' => 'Pendiente',
                                                'completed' => 'Completado',
                                            ]
                                    )
                                    ->default('completed')
                                    ->required()
                                    ->native(false)
                                    ->disabled(fn(?Purchase $record): bool => filled($record))
                                    ->dehydrated(fn(?Purchase $record): bool => blank($record))
                                    ->helperText(
                                        fn(?Purchase $record): string => $record
                                            ? 'El estado se cambia mediante acciones controladas.'
                                            : 'Completado suma stock. Pendiente queda como borrador y no mueve inventario.'
                                    )
                                    ->columnSpan(1),
                            ]),
                    ])->columnSpan(['lg' => 3]),

                    Forms\Components\Group::make()->schema([
                        Forms\Components\Section::make('Resumen Financiero')
                            ->icon('heroicon-o-calculator')
                            ->schema([
                                Forms\Components\Placeholder::make('subtotal_label')
                                    ->label('Subtotal (Op. Gravadas)')
                                    ->content(fn(\Filament\Forms\Get $get): string => 'S/ ' . number_format((float)($get('subtotal') ?? 0), 2))
                                    ->extraAttributes(['class' => 'flex justify-between border-b pb-2']),

                                Forms\Components\Placeholder::make('igv_label')
                                    ->label('IGV (18%)')
                                    ->content(fn(\Filament\Forms\Get $get): string => 'S/ ' . number_format((float)($get('igv') ?? 0), 2))
                                    ->extraAttributes(['class' => 'flex justify-between border-b pb-2']),

                                Forms\Components\Placeholder::make('total_label')
                                    ->label('TOTAL A PAGAR')
                                    ->content(fn(\Filament\Forms\Get $get): string => 'S/ ' . number_format((float)($get('total') ?? 0), 2))
                                    ->extraAttributes(['class' => 'flex justify-between text-2xl font-black text-primary-600 pt-2']),

                                Forms\Components\Hidden::make('subtotal'),
                                Forms\Components\Hidden::make('igv'),
                                Forms\Components\Hidden::make('total'),
                            ]),
                    ])->columnSpan(['lg' => 1]),
                ]), // Fin del Grid Principal

                // =========================================================
                // 🌟 CUERPO (Medio): Tabla de Productos a 100% de Ancho
                // =========================================================
                Forms\Components\Section::make('Detalle de Productos')
                    ->description('Agrega los productos comprados')
                    ->icon('heroicon-o-cube')
                    ->columnSpanFull() // 🚀 TRUCO DE DISEÑO: Esto le da el 100% del ancho de la pantalla
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->live()
                            ->afterStateUpdated(fn(\Filament\Forms\Get $get, \Filament\Forms\Set $set) => self::updateTotals($get, $set))
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action
                                    ->after(fn(\Filament\Forms\Get $get, \Filament\Forms\Set $set) => self::updateTotals($get, $set))
                                    ->requiresConfirmation()
                                    ->modalHeading('Eliminar Producto')
                            )
                            // 🌟 LA MAGIA CORREGIDA: Leemos, calculamos y luego BORRAMOS la evidencia
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                                $isFractionable = $data['_is_fractionable'] ?? false;
                                $presentacion = $data['measurement_unit'] ?? 'box';

                                // 1. Hacemos la matemática si eligieron Unidad
                                if ($isFractionable && $presentacion === 'unit') {
                                    $product = \Percy\Core\Models\Product::find($data['product_id']);
                                    if ($product && $product->units_per_box > 0) {
                                        // Convertimos (Ej: 50 unidades / 100 = 0.5 cajas)
                                        $data['quantity'] = $data['quantity'] / $product->units_per_box;
                                        $data['unit_cost'] = $data['unit_cost'] * $product->units_per_box;
                                    }
                                }

                                // 2. 🚨 LIMPIEZA DE SEGURIDAD 🚨
                                // Borramos todos los campos temporales del formulario para que
                                // Laravel no intente guardarlos en la tabla purchase_items y lance error SQL

                                if (!empty($data['batch_number'])) {
                                    $data['batch_number'] = strtoupper(trim($data['batch_number']));
                                }

                                unset($data['_is_fractionable']);
                                unset($data['_is_weighable']);
                                unset($data['unit_code']);
                                unset($data['measurement_unit']);
                                unset($data['_existing_batch_id']);
                                unset($data['_existing_batch_expiration_date']);
                                unset($data['_batch_expiration_warning_message']);

                                return $data;
                            })
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->relationship(
                                        'product',
                                        'name',
                                        modifyQueryUsing: fn(Builder $query) => self::scopeToCurrentTenant($query)
                                            ->where('active', true)
                                    )
                                    ->label('Producto')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, \Filament\Forms\Set $set, \Filament\Forms\Get $get) {
                                        if ($state) {
                                            $product = self::scopeToCurrentTenant(
                                                Product::query()->with('unidadSunat')
                                            )->find($state);

                                            if (!$product) {
                                                return;
                                            }

                                            $set('unit_cost', $product->cost ?? 0);
                                            $set('_is_fractionable', $product->is_fractionable);
                                            $set('_is_weighable', $product->is_weighable ?? false);
                                            $set('unit_code', $product->unidadSunat ? $product->unidadSunat->codigo : 'NIU');
                                            $set('measurement_unit', 'box');
                                        }
                                        self::updateRow($get, $set);
                                        self::updateTotals($get, $set);
                                    })
                                    // 🌟 MEJORA: Ancho dinámico inteligente
                                    ->columnSpan([
                                        'default' => 12, // En celular ocupa el 100%
                                        'md' => function (\Filament\Forms\Get $get) {
                                            $features = self::tenantFeatures();
                                            $isPharmacy = ($features['has_lots'] ?? false) || ($features['has_expiry_dates'] ?? false);

                                            if ($isPharmacy) {
                                                return $get('_is_fractionable') ? 4 : 6;
                                            }
                                            return 6; // Tienda general
                                        }
                                    ]),

                                // 🌟 NUEVO CAMPO: Selector de Presentación para Compras
                                Forms\Components\Select::make('measurement_unit')
                                    ->label('Presentación')
                                    ->options(['box' => 'Caja', 'unit' => 'Unidad'])
                                    ->visible(fn(Get $get) => $get('_is_fractionable'))
                                    ->required(fn(Get $get) => $get('_is_fractionable'))
                                    ->default('box')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($productId = $get('product_id')) {
                                            $product = self::scopeToCurrentTenant(Product::query())->find($productId);
                                            if ($product) {
                                                if ($state === 'unit' && $product->units_per_box > 0) {
                                                    // Calcula el costo de la pastilla individual
                                                    $set('unit_cost', round($product->cost / $product->units_per_box, 4));
                                                } else {
                                                    $set('unit_cost', $product->cost);
                                                }
                                            }
                                        }
                                        self::updateRow($get, $set);
                                        self::updateTotals($get, $set);
                                    })
                                    // 🌟 Fijo de 2 columnas
                                    ->columnSpan(['default' => 12, 'md' => 2]),

                                Forms\Components\TextInput::make('batch_number')
                                    ->label('N° de Lote')
                                    ->visible(function () {
                                        $features = self::tenantFeatures();

                                        return $features['has_lots'] ?? false;
                                    })
                                    ->required(fn() => Auth::user()->tenant->businessSector->features['has_lots'] ?? false)
                                    ->datalist(function (Get $get): array {
                                        $productId = $get('product_id');

                                        if (!$productId) {
                                            return [];
                                        }

                                        return ProductBatch::query()
                                            ->where('tenant_id', Auth::user()?->tenant_id)
                                            ->where('product_id', $productId)
                                            ->where('is_active', true)
                                            ->orderBy('expiration_date')
                                            ->pluck('batch_number')
                                            ->filter()
                                            ->unique()
                                            ->values()
                                            ->toArray();
                                    })
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $batchNumber = strtoupper(trim((string) $state));

                                        $set('_batch_expiration_warning_message', null);

                                        if ($batchNumber === '') {
                                            $set('_existing_batch_id', null);
                                            $set('_existing_batch_expiration_date', null);

                                            return;
                                        }

                                        $set('batch_number', $batchNumber);

                                        $batch = self::findExistingBatchForCurrentTenant(
                                            (int) $get('product_id'),
                                            $batchNumber
                                        );

                                        if (!$batch) {
                                            $set('_existing_batch_id', null);
                                            $set('_existing_batch_expiration_date', null);

                                            return;
                                        }

                                        $set('_existing_batch_id', $batch->id);

                                        if ($batch->expiration_date) {
                                            $expirationDate = $batch->expiration_date->format('Y-m-d');

                                            $set('_existing_batch_expiration_date', $expirationDate);
                                            $set('expiration_date', $expirationDate);
                                        }

                                        Notification::make()
                                            ->title('Lote existente encontrado')
                                            ->body('El stock se sumará al lote ' . $batch->batch_number . '.')
                                            ->success()
                                            ->send();
                                    })
                                    ->helperText(function (Get $get): string {
                                        if ($get('_existing_batch_id')) {
                                            return 'Lote existente seleccionado. La compra sumará stock a este lote.';
                                        }

                                        return 'Puedes seleccionar un lote existente o escribir uno nuevo.';
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 3]),

                                Forms\Components\DatePicker::make('expiration_date')
                                    ->label('Vencimiento')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->live()
                                    ->visible(function () {
                                        $features = self::tenantFeatures();

                                        return $features['has_expiry_dates'] ?? false;
                                    })
                                    ->required(fn() => Auth::user()->tenant->businessSector->features['has_expiry_dates'] ?? false)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $message = self::batchExpirationMismatchMessage($get, $state);

                                        $set('_batch_expiration_warning_message', $message);
                                    })
                                    ->rules([
                                        fn(Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $message = self::batchExpirationMismatchMessage($get, $value);

                                            if ($message) {
                                                $fail($message);
                                            }
                                        },
                                    ])
                                    ->helperText(function (Get $get): string {
                                        if ($get('_existing_batch_expiration_date')) {
                                            return 'Fecha tomada del lote existente.';
                                        }

                                        return 'Ingresa la fecha de vencimiento del lote nuevo.';
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 3]),

                                Forms\Components\Placeholder::make('batch_expiration_warning')
                                    ->label('')
                                    ->visible(fn(Get $get): bool => filled($get('_batch_expiration_warning_message')))
                                    ->content(function (Get $get) {
                                        return new HtmlString(
                                            '<div class="rounded-xl border border-danger-300 bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-900/20 dark:text-danger-300">
                                                <strong>⚠ Lote con fecha diferente</strong><br>' .
                                                e($get('_batch_expiration_warning_message')) .
                                                '</div>'
                                        );
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 6]),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(fn(\Filament\Forms\Get $get) => $get('_is_weighable') ? 0.001 : 1)
                                    ->step(fn(\Filament\Forms\Get $get) => $get('_is_weighable') ? 0.001 : 1)
                                    ->suffix(function (\Filament\Forms\Get $get) {
                                        if ($get('_is_fractionable')) {
                                            return $get('measurement_unit') === 'unit' ? 'Und' : 'Caj';
                                        }
                                        if (!$get('_is_weighable')) return 'Und';
                                        return match ($get('unit_code')) {
                                            'KGM' => 'Kg',
                                            'LTR' => 'Lt',
                                            'GLL' => 'Gal',
                                            default => $get('unit_code') ?? 'Und'
                                        };
                                    })
                                    ->rules([
                                        fn(\Filament\Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            if (!$get('_is_weighable') && fmod((float) $value, 1) !== 0.0) {
                                                $fail('Este producto solo admite cantidades enteras.');
                                            }
                                        },
                                    ])
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(\Filament\Forms\Get $get, \Filament\Forms\Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)])
                                    // 🌟 MEJORA: Mucho más espacio para respirar
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => function () {
                                            $features = self::tenantFeatures();
                                            $isPharmacy = ($features['has_lots'] ?? false) || ($features['has_expiry_dates'] ?? false);
                                            return $isPharmacy ? 4 : 2;
                                        }
                                    ]),

                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Costo Inc. IGV')
                                    ->numeric()
                                    ->required()
                                    ->prefix('S/')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(\Filament\Forms\Get $get, \Filament\Forms\Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)])
                                    // 🌟 MEJORA: Suficiente espacio para el prefijo "S/" y los decimales
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => function () {
                                            $features = self::tenantFeatures();
                                            $isPharmacy = ($features['has_lots'] ?? false) || ($features['has_expiry_dates'] ?? false);
                                            return $isPharmacy ? 4 : 2;
                                        }
                                    ]),

                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->readonly()
                                    ->prefix('S/')
                                    ->dehydrated()
                                    // 🌟 MEJORA: Ancho equitativo con los otros dos
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => function () {
                                            $features = self::tenantFeatures();
                                            $isPharmacy = ($features['has_lots'] ?? false) || ($features['has_expiry_dates'] ?? false);
                                            return $isPharmacy ? 4 : 2;
                                        }
                                    ]),

                                // CAMPOS OCULTOS
                                Forms\Components\Hidden::make('_is_fractionable'),
                                Forms\Components\Hidden::make('_is_weighable')->default(false),
                                Forms\Components\Hidden::make('unit_code')->default('NIU'),
                                Forms\Components\Hidden::make('_existing_batch_id'),
                                Forms\Components\Hidden::make('_existing_batch_expiration_date'),
                                Forms\Components\Hidden::make('_batch_expiration_warning_message'),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->addActionLabel('+ Agregar Producto')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(function (array $state): ?string {
                                if (empty($state['product_id'])) {
                                    return 'Nuevo producto';
                                }

                                return self::scopeToCurrentTenant(Product::query())
                                    ->find($state['product_id'])?->name ?? 'Producto no disponible';
                            }),
                    ]),

                // =========================================================
                // 🌟 PIE (Bottom): Notas Adicionales
                // =========================================================
                Forms\Components\Section::make('Notas Adicionales')
                    ->icon('heroicon-o-document-text')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Observaciones')
                            ->rows(3)
                            ->placeholder('Agrega notas o comentarios sobre esta compra...')
                    ])->collapsible(),
            ]);
    }

    private static function findExistingBatchForCurrentTenant(?int $productId, ?string $batchNumber): ?ProductBatch
    {
        if (!$productId || blank($batchNumber)) {
            return null;
        }

        return ProductBatch::query()
            ->where('tenant_id', Auth::user()?->tenant_id)
            ->where('product_id', $productId)
            ->where('batch_number', strtoupper(trim($batchNumber)))
            ->first();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->description(fn(Purchase $record): string => $record->purchase_date->diffForHumans()),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-building-office-2')
                    ->weight('bold')
                    ->description(fn(Purchase $record): ?string => $record->document_number ? "Doc: {$record->document_number}" : null),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Productos')
                    ->counts('items')
                    ->icon('heroicon-o-cube')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->icon('heroicon-o-banknotes'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'completed' => 'Completado',
                        'canceled' => 'Cancelado',
                        default => $state,
                    })

                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'pending' => 'heroicon-o-clock',
                        'completed' => 'heroicon-o-check-circle',
                        'canceled' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('purchase_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->multiple()
                    ->options([
                        'pending' => 'Pendiente',
                        'completed' => 'Completado',
                        'canceled' => 'Cancelado',
                    ])
                    ->indicator('Estado'),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Proveedor')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->indicator('Proveedor'),

                Tables\Filters\Filter::make('purchase_date')
                    ->label('Rango de Fechas')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->columns(2) //
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn(Builder $query, $date) => $query->whereDate('purchase_date', '>=', $date))
                            ->when($data['until'], fn(Builder $query, $date) => $query->whereDate('purchase_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Desde: ' . \Carbon\Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Hasta: ' . \Carbon\Carbon::parse($data['until'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver detalles')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->modalHeading('Detalles de la Compra')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->modalWidth('6xl'),

                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil')
                        ->color('warning')
                        ->visible(
                            fn(Purchase $record): bool =>
                            $record->status === 'pending' &&
                                (Auth::user()?->canEditPurchases() ?? false)
                        ),

                    Tables\Actions\Action::make('confirmPendingPurchase')
                        ->label('Confirmar compra')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(
                            fn(Purchase $record): bool =>
                            $record->status === 'pending' &&
                                (Auth::user()?->canEditPurchases() ?? false)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Confirmar compra pendiente')
                        ->modalDescription('Al confirmar esta compra, los productos ingresarán al inventario y se registrará el movimiento en Kardex. Luego ya no podrá editarse libremente.')
                        ->modalSubmitActionLabel('Sí, confirmar')
                        ->modalCancelActionLabel('Cancelar')
                        ->action(function (Purchase $record): void {
                            try {
                                app(PurchaseService::class)->confirmPendingPurchase($record);

                                Notification::make()
                                    ->success()
                                    ->title('Compra confirmada')
                                    ->body('La compra fue confirmada y el stock ingresó correctamente al inventario.')
                                    ->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('No se pudo confirmar la compra')
                                    ->body(collect($e->errors())->flatten()->first() ?? 'Verifica la compra antes de confirmar.')
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('cancelPendingPurchase')
                        ->label('Cancelar compra')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(
                            fn(Purchase $record): bool =>
                            $record->status === 'pending' &&
                                (Auth::user()?->canEditPurchases() ?? false)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Cancelar compra pendiente')
                        ->modalDescription('Esta compra aún no afecta inventario. Se marcará como cancelada y quedará como historial.')
                        ->modalSubmitActionLabel('Sí, cancelar compra')
                        ->modalCancelActionLabel('Volver')
                        ->action(function (Purchase $record): void {
                            try {
                                app(PurchaseService::class)->cancelPendingPurchase($record);

                                Notification::make()
                                    ->success()
                                    ->title('Compra cancelada')
                                    ->body('La compra pendiente fue cancelada correctamente.')
                                    ->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('No se pudo cancelar la compra')
                                    ->body(collect($e->errors())->flatten()->first() ?? 'Verifica la compra antes de cancelar.')
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('voidCompletedPurchase')
                        ->label('Anular compra')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->visible(
                            fn(Purchase $record): bool =>
                            $record->status === 'completed' &&
                                (Auth::user()?->canVoidCompletedPurchases() ?? false)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Anular compra completada')
                        ->modalDescription('Esta acción descontará del inventario los productos ingresados por esta compra y registrará un movimiento de salida en Kardex. No podrás editar la compra después de anularla.')
                        ->modalSubmitActionLabel('Sí, anular compra')
                        ->modalCancelActionLabel('Volver')
                        ->form([
                            Forms\Components\Textarea::make('void_reason')
                                ->label('Motivo de anulación')
                                ->placeholder('Ej: Documento registrado por error, compra duplicada, devolución al proveedor, etc.')
                                ->required()
                                ->maxLength(500)
                                ->rows(3),
                        ])
                        ->action(function (Purchase $record, array $data): void {
                            try {
                                app(PurchaseService::class)->voidCompletedPurchase(
                                    $record,
                                    $data['void_reason'] ?? null
                                );

                                Notification::make()
                                    ->success()
                                    ->title('Compra anulada')
                                    ->body('La compra fue anulada y el stock fue descontado correctamente.')
                                    ->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('No se pudo anular la compra')
                                    ->body(collect($e->errors())->flatten()->first() ?? 'Verifica el stock antes de anular.')
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar borrador')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(
                            fn(Purchase $record): bool =>
                            $record->status === 'pending' &&
                                (Auth::user()?->canDeletePurchases() ?? false)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar compra pendiente')
                        ->modalDescription('Esta compra aún no afecta inventario. Puedes eliminarla si fue registrada por error.')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->modalCancelActionLabel('Cancelar'),
                ])
                    ->label('Acciones')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make()
                ]),
            ])
            ->emptyStateHeading('No hay compras registradas')
            ->emptyStateDescription('Comienza registrando tu primera compra usando el botón de arriba.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }

    public static function updateRow(Get $get, Set $set): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $unitCost = (float) ($get('unit_cost') ?? 0); // Asumimos que este costo YA INCLUYE IGV

        // El subtotal de la fila ahora representa el Monto Total de esa fila
        $totalFila = $quantity * $unitCost;

        $set('subtotal', round($totalFila, 2));
    }

    public static function updateTotals(Get $get, Set $set): void
    {
        $items = $get('items');
        if ($items === null) {
            $items = $get('../../items') ?? [];
            $prefix = '../../';
        } else {
            $prefix = '';
        }

        $totalGeneral = 0; // Sumaremos el total final de todas las filas

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $cost = (float) ($item['unit_cost'] ?? 0); // Costo con IGV incluido

            $totalGeneral += ($qty * $cost);
        }

        // 🌟 MATEMÁTICA INVERSA
        // 1. Calculamos la base (Subtotal sin IGV) dividiendo entre 1.18
        $igvRate = app(TenantPricingService::class)->igvRate();
        $factor = 1 + $igvRate;

        $subtotal = $factor > 0 ? ($totalGeneral / $factor) : $totalGeneral;

        // 2. El IGV es la diferencia entre el Total y la Base (así evitamos descuadres de céntimos)
        $igv = $totalGeneral - $subtotal;

        // Guardamos los valores redondeados
        $set($prefix . 'subtotal', round($subtotal, 2)); // Op. Gravadas
        $set($prefix . 'igv', round($igv, 2));           // IGV (18%)
        $set($prefix . 'total', round($totalGeneral, 2)); // Total a Pagar
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }
}
