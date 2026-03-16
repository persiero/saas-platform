<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers;
use App\Services\SunatService;
use Percy\Core\Models\Sale;
use Percy\Core\Models\Product;
use Percy\Core\Models\AfectacionIgv;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Tables\Actions\Action;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Ventas';
    protected static ?string $modelLabel = 'Venta';
    protected static ?string $pluralModelLabel = 'Ventas';
    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id);
    }

    // Nadie edita una venta ya hecha.
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    // Nadie borra una venta ya hecha (se anulan con Nota de Crédito, no se borran de la base de datos).
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    // Todos pueden crear ventas (Cajeros y Admins).
    public static function canCreate(): bool
    {
        return true;
    }

    // Asegurarnos de que toda nueva venta guarde el ID del cajero y el tenant
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['tenant_id'] = Auth::user()->tenant_id;

        return $data;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Información de Venta')->schema([
                        Forms\Components\Select::make('document_type')
                            ->label('Tipo de Comprobante')
                            ->options([
                                '03' => 'Boleta Electrónica',
                                '01' => 'Factura Electrónica',
                            ])
                            ->required()
                            ->default('03')
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Buscamos la serie, pero ESTRICTAMENTE de esta farmacia
                                $serieAuto = \Percy\Core\Models\Serie::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                                    ->where('document_type', $state)
                                    ->where('active', true)
                                    ->value('serie');

                                $set('series', $serieAuto);
                            }),

                        Forms\Components\Select::make('series')
                            ->label('Serie')
                            ->required()
                            ->options(function (Get $get) {
                                $docType = $get('document_type');
                                if (!$docType) return [];

                                // Traemos las opciones, ESTRICTAMENTE de esta farmacia
                                return \Percy\Core\Models\Serie::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                                    ->where('document_type', $docType)
                                    ->where('active', true)
                                    ->pluck('serie', 'serie');
                            })
                            ->default(function () {
                                return \Percy\Core\Models\Serie::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                                    ->where('document_type', '03')
                                    ->where('active', true)
                                    ->value('serie');
                            })
                            ->selectablePlaceholder(false), // Oculta la opción "Select an option" si ya hay datos

                        Forms\Components\Hidden::make('correlative'),


                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'name', function (Builder $query, Get $get) {
                                $docType = $get('document_type'); // '01' Factura, '03' Boleta

                                return $query->when($docType === '01', function ($q) {
                                    // Solo clientes con RUC para Facturas
                                    // (Asegúrate de que en tu BD el código para RUC sea '6' o 'RUC')
                                    return $q->whereIn('document_type', ['6', 'RUC']);
                                })->when($docType === '03', function ($q) {
                                    // DNI y otros para Boletas
                                    return $q->whereIn('document_type', ['1', 'DNI', '0', '7', '4']);
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->live() // Para que se refresque si cambias el tipo de comprobante
                            ->required(fn (Get $get) => $get('document_type') === '01') // Solo obligatorio si es Factura
                            ->helperText(fn (Get $get) => $get('document_type') === '01' ? 'Obligatorio para Facturas' : 'Opcional. Déjalo en blanco para Consumidor Final.')
                            ->columnSpan(2),

                        Forms\Components\Select::make('payment_method')
                            ->label('Método de Pago')
                            ->options(Sale::PAYMENT_METHODS)
                            ->default('Efectivo')
                            ->live()
                            ->required()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('payment_reference', null)),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('N° de Operación / Referencia')
                            ->placeholder('Ej: 123456')
                            ->visible(fn (\Filament\Forms\Get $get) => Sale::requiresReference($get('payment_method') ?? ''))
                            ->required(fn (\Filament\Forms\Get $get) => Sale::requiresReference($get('payment_method') ?? '')),

                        Forms\Components\DateTimePicker::make('sold_at')
                            ->label('Fecha de Emisión')
                            ->default(now())
                            ->minDate(now()->subDays(7))
                            ->maxDate(now())
                            ->helperText('SUNAT solo acepta documentos de los últimos 7 días')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'completed' => 'Completado',
                                'pending' => 'Pendiente',
                                'canceled' => 'Anulado',
                            ])
                            ->default('completed')
                            ->hidden(), // Lo ocultamos de la vista del cajero para no confundir
                    ])->columns(4),

                    Forms\Components\Section::make('Detalle de Productos')->schema([

                        // 🔫 LECTOR DE CÓDIGO DE BARRAS
                        Forms\Components\TextInput::make('scanner')
                            ->label('Lector de Código de Barras')
                            ->placeholder('Dispare la pistola aquí...')
                            ->prefixIcon('heroicon-o-qr-code')
                            ->autofocus() // El cursor siempre estará aquí al abrir la venta
                            // Evitamos que el "Enter" de la pistola guarde la venta accidentalmente
                            ->extraInputAttributes(['x-on:keydown.enter.prevent' => '$wire.$refresh()'])
                            ->live(debounce: 250) // Espera un cuarto de segundo a que la pistola termine de escribir
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (empty($state)) return;

                                // Buscamos el producto por su código de barras
                                $product = \Percy\Core\Models\Product::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                                    ->where('barcode', $state)
                                    ->first();

                                if ($product) {
                                    // Traemos los items actuales del carrito
                                    $items = $get('items') ?? [];

                                    // Buscamos el lote FEFO igual que arriba
                                    $batch = \Percy\Core\Models\ProductBatch::where('product_id', $product->id)
                                        ->where('current_quantity', '>', 0)
                                        ->whereDate('expiration_date', '>=', now())
                                        ->orderBy('expiration_date', 'asc')
                                        ->first();

                                    // CALCULAMOS LOS IMPUESTOS FALTANTES PARA QUE LA BD NO EXPLOTE
                                    $afectacion = \Percy\Core\Models\AfectacionIgv::find($product->afectacion_igv_id ?? 1);
                                    $porcentaje = ($afectacion && $afectacion->gravado) ? ($afectacion->porcentaje / 100) : 0;
                                    $unitValue = $product->price / (1 + $porcentaje);
                                    $igvAmount = $product->price - $unitValue;

                                    // INYECTAMOS EL PRODUCTO CON TODOS SUS DATOS COMPLETOS
                                    $items[(string) \Illuminate\Support\Str::uuid()] = [
                                        'product_id' => $product->id,
                                        'product_batch_id' => $batch ? $batch->id : null,
                                        'measurement_unit' => 'box',
                                        'quantity' => 1,
                                        'unit_price' => $product->price,
                                        'total' => $product->price,
                                        'afectacion_igv_id' => $product->afectacion_igv_id,
                                        'unit_code' => $product->unidadSunat ? $product->unidadSunat->codigo : 'NIU',
                                        '_stock_disponible' => $product->current_stock,
                                        '_is_fractionable' => $product->is_fractionable,
                                        '_box_price' => $product->price,
                                        '_fraction_price' => $product->unit_price,
                                        // 🌟 LOS CAMPOS FALTANTES QUE CAUSARON EL ERROR:
                                        'item_name' => $product->name,
                                        'unit_value' => round($unitValue, 2),
                                        'igv_amount' => round($igvAmount, 2),
                                    ];

                                    // Guardamos el carrito actualizado
                                    $set('items', $items);

                                    // Forzamos la actualización de los totales globales
                                    self::updateTotals($get, $set);

                                    \Filament\Notifications\Notification::make()->title('Agregado: ' . $product->name)->success()->send();
                                } else {
                                    \Filament\Notifications\Notification::make()->title('Código no encontrado')->danger()->send();
                                }

                                // Limpiamos la caja para el siguiente disparo láser
                                $set('scanner', null);
                            })
                            ->columnSpanFull(), // Ocupa todo el ancho

                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->live() // Escucha cambios globales en el repetidor (como eliminar filas)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateTotals($get, $set);
                            })
                            ->deleteAction(
                                fn (\Filament\Forms\Components\Actions\Action $action) => $action->after(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                            )
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->label('Producto')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(3)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            $set('unit_price', $product->price);
                                            $set('afectacion_igv_id', $product->afectacion_igv_id);
                                            $set('unit_code', $product->unidadSunat ? $product->unidadSunat->codigo : 'NIU');
                                            $set('item_name', $product->name);
                                            $set('_stock_disponible', $product->current_stock);

                                            // MAGIA FARMACIA: Guardamos en secreto los datos de fracción
                                            $set('_is_fractionable', $product->is_fractionable);
                                            $set('_box_price', $product->price);
                                            $set('_fraction_price', $product->unit_price);
                                            $set('measurement_unit', 'box'); // Por defecto se vende la caja

                                            // 🌟 NUEVA MAGIA FEFO: Auto-seleccionar el lote más próximo a vencer
                                            $loteProximo = \Percy\Core\Models\ProductBatch::where('product_id', $state)
                                                ->where('current_quantity', '>', 0)
                                                ->whereDate('expiration_date', '>=', now())
                                                ->orderBy('expiration_date', 'asc') // Del más viejo al más nuevo
                                                ->where('is_active', true)
                                                ->first();

                                            // Si encontró un lote, lo asigna directo al campo 'product_batch_id'
                                            $set('product_batch_id', $loteProximo ? $loteProximo->id : null);
                                        }
                                        self::updateRow($get, $set);
                                        self::updateTotals($get, $set);
                                    }),

                                // ¡NUEVO CAMPO! Solo aparece si el producto es fraccionable
                                Forms\Components\Select::make('measurement_unit')
                                    ->label('Present.')
                                    ->options([
                                        'box' => 'Caja',
                                        'unit' => 'Unidad',
                                    ])
                                    ->visible(fn (Get $get) => $get('_is_fractionable'))
                                    ->required(fn (Get $get) => $get('_is_fractionable'))
                                    ->columnSpan(1)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        // Si cambia de Caja a Unidad, cambiamos el precio automáticamente
                                        if ($state === 'unit') {
                                            $set('unit_price', $get('_fraction_price'));
                                        } else {
                                            $set('unit_price', $get('_box_price'));
                                        }
                                        // Recalculamos la fila y los totales globales
                                        self::updateRow($get, $set);
                                        self::updateTotals($get, $set);
                                    }),

                                // 🌟 3. ¡NUEVO CAMPO! EL SELECTOR DE LOTE (Solo para Farmacias)
                                Forms\Components\Select::make('product_batch_id')
                                    ->label('Lote')
                                    ->options(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) return [];

                                        // Solo traemos los lotes de este producto que tengan stock > 0
                                        return \Percy\Core\Models\ProductBatch::where('product_id', $productId)
                                            ->where('current_quantity', '>', 0)
                                            ->whereDate('expiration_date', '>=', now()) // No muestra lotes vencidos
                                            ->orderBy('expiration_date', 'asc') // FEFO
                                            ->where('is_active', true)
                                            // Formateamos para que el cajero vea el Lote y su fecha de vencimiento
                                            ->get()
                                            ->mapWithKeys(function ($batch) {
                                                $vence = $batch->expiration_date ? $batch->expiration_date->format('d/m/Y') : 'N/A';
                                                return [$batch->id => "{$batch->batch_number} (Vence: {$vence})"];
                                            });
                                    })
                                    ->visible(function () {
                                        $sector = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->name ?? '';
                                        return str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica');
                                    })
                                    // Es requerido SOLO si el negocio es una farmacia
                                    ->required(function () {
                                        $sector = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->name ?? '';
                                        return str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3), // Ajusta los columnSpan de los demás campos para que sumen 12

                                //Forms\Components\Hidden::make('afectacion_igv_id')
                                  //  ->relationship('afectacionIgv', 'descripcion')
                                  //  ->label('IGV')
                                   // ->required()
                                    //->columnSpan(1)
                                    //->live()
                                    //->afterStateUpdated(fn(Get $get, Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)]),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cant.')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->maxValue(function (Get $get) {
                                        $stock = null;
                                        if ($batchId = $get('product_batch_id')) {
                                            $batch = \Percy\Core\Models\ProductBatch::find($batchId);
                                            $stock = $batch ? $batch->current_quantity : null;
                                        } elseif ($productId = $get('product_id')) {
                                            $product = \Percy\Core\Models\Product::find($productId);
                                            $stock = $product ? $product->current_stock : null;
                                        }

                                        if ($stock === null) return null;

                                        // Si está vendiendo por UNIDAD, quitamos el límite estricto para que pueda poner 20, 50 o 100 pastillas.
                                        if ($get('measurement_unit') === 'unit') {
                                            return 99999;
                                        }

                                        // Si vende por CAJA, respeta el stock exacto del lote (Ej: 9)
                                        return $stock;
                                    })
                                    ->required()
                                    ->columnSpan(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)])
                                    // MEJORA UX: Le mostramos a la cajera el stock del lote vs el total
                                    ->helperText(function (Get $get) {
                                        if (!$get('product_id')) return null;

                                        $totalStock = $get('_stock_disponible') ?? 0;

                                        if ($batchId = $get('product_batch_id')) {
                                            $batch = \Percy\Core\Models\ProductBatch::find($batchId);
                                            $loteStock = $batch ? $batch->current_quantity : 0;
                                            return "Lote: {$loteStock} | Total: {$totalStock}";
                                        }

                                        return "Stock Total: {$totalStock}";
                                    })
                                    ->required()
                                    ->columnSpan(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)])
                                    // Magia UX: Mostrar el stock como texto de ayuda para no gastar columnas
                                    ->helperText(fn (Get $get) => $get('product_id') ? "Stock: " . ($get('_stock_disponible') ?? 0) : null),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Precio Unit.')
                                    ->numeric()
                                    ->required()
                                    ->columnSpan(2)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)]),

                                Forms\Components\TextInput::make('total')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->required()
                                    ->readonly()
                                    ->columnSpan(2),

                                // CAMPOS OCULTOS: Se calculan solos y van a la BD para SUNAT, pero no ensucian la pantalla
                                Forms\Components\Hidden::make('_stock_disponible'),
                                Forms\Components\Hidden::make('item_name'),
                                Forms\Components\Hidden::make('unit_value'),
                                Forms\Components\Hidden::make('afectacion_igv_id'),
                                Forms\Components\Hidden::make('igv_amount'),
                                Forms\Components\Hidden::make('unit_code')->default('NIU'),

                                // Memoria temporal para la magia de la farmacia
                                Forms\Components\Hidden::make('_is_fractionable'),
                                Forms\Components\Hidden::make('_box_price'),
                                Forms\Components\Hidden::make('_fraction_price'),
                            ])
                            ->columns(12) // Pasamos de 16 a 12 columnas. ¡Diseño perfecto!
                            ->defaultItems(0)
                            ->addActionLabel('Agregar otro producto'),
                    ]),
                ])->columnSpan(['lg' => 3]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Resumen Financiero')->schema([
                        Forms\Components\Placeholder::make('op_gravadas_lbl')
                            ->label('Op. Gravadas')
                            ->content(fn (Get $get): string => 'S/ ' . number_format((float)($get('op_gravadas') ?? 0), 2))
                            ->extraAttributes(['class' => 'flex justify-between border-b pb-1']),

                        Forms\Components\Placeholder::make('op_exoneradas_lbl')
                            ->label('Op. Exoneradas')
                            ->content(fn (Get $get): string => 'S/ ' . number_format((float)($get('op_exoneradas') ?? 0), 2))
                            ->extraAttributes(['class' => 'flex justify-between border-b pb-1 text-gray-500']),

                        Forms\Components\Placeholder::make('op_inafectas_lbl')
                            ->label('Op. Inafectas')
                            ->content(fn (Get $get): string => 'S/ ' . number_format((float)($get('op_inafectas') ?? 0), 2))
                            ->extraAttributes(['class' => 'flex justify-between border-b pb-1 text-gray-500']),

                        // EL NUEVO SUBTOTAL (Suma de todas las bases antes de impuestos)
                        Forms\Components\Placeholder::make('subtotal_lbl')
                            ->label('SUBTOTAL')
                            ->content(function (Get $get) {
                                $sub = (float)($get('op_gravadas') ?? 0) + (float)($get('op_exoneradas') ?? 0) + (float)($get('op_inafectas') ?? 0);
                                return 'S/ ' . number_format($sub, 2);
                            })
                            ->extraAttributes(['class' => 'flex justify-between border-b pb-1 font-semibold text-gray-700 dark:text-gray-300']),

                        Forms\Components\Placeholder::make('igv_lbl')
                            ->label('IGV (18%)')
                            ->content(fn (Get $get): string => 'S/ ' . number_format((float)($get('igv') ?? 0), 2))
                            ->extraAttributes(['class' => 'flex justify-between border-b pb-1']),

                        Forms\Components\Placeholder::make('total_lbl')
                            ->label('IMPORTE TOTAL')
                            ->content(fn (Get $get): string => 'S/ ' . number_format((float)($get('total') ?? 0), 2))
                            ->extraAttributes(['class' => 'flex justify-between text-2xl font-black text-primary-600 pt-2']),

                        // Mantenemos los Hidden para que la BD reciba los datos correctamente
                        Forms\Components\Hidden::make('op_gravadas'),
                        Forms\Components\Hidden::make('op_exoneradas'),
                        Forms\Components\Hidden::make('op_inafectas'),
                        Forms\Components\Hidden::make('igv'),
                        Forms\Components\Hidden::make('total'),
                    ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(4);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información del Comprobante')
                    ->schema([
                        TextEntry::make('document_type')
                            ->label('Comprobante')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                '01' => 'Factura',
                                '03' => 'Boleta',
                                '07' => 'Nota de Crédito',
                                '08' => 'Nota de Débito',
                                default => $state,
                            })
                            ->badge()
                            ->color('info'),

                        TextEntry::make('series')
                            ->label('Serie')
                            ->weight('bold'),

                        // Si tienes un campo de correlativo en tu BD, ponlo aquí. Si no, bórralo.
                        TextEntry::make('correlative')
                            ->label('Correlativo')
                            ->weight('bold'),

                        TextEntry::make('customer.name')
                            ->label('Cliente')
                            ->icon('heroicon-o-user'),

                        // AGREGA ESTE NUEVO CAMPO:
                        TextEntry::make('user.name')
                            ->label('Atendido por (Cajero)')
                            ->icon('heroicon-o-identification')
                            ->weight('bold'),

                        TextEntry::make('sold_at')
                            ->label('Fecha de Emisión')
                            ->dateTime('d/m/Y h:i A'),
                    ])->columns(5),

                Section::make('Detalle de Productos')
                    ->schema([
                        RepeatableEntry::make('items') // Asegúrate que tu relación de ítems se llame 'items'
                            ->label('')
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Producto')
                                    ->weight('bold'),

                                TextEntry::make('quantity')
                                    ->label('Cant.')
                                    ->badge()
                                    ->color('gray'),

                                TextEntry::make('unit_price')
                                    ->label('Precio Unit.')
                                    ->money('PEN'),

                                TextEntry::make('total')
                                    ->label('Subtotal')
                                    ->money('PEN')
                                    ->color('success')
                                    ->weight('bold'),
                            ])
                            ->columns(4)
                    ]),

                Section::make('Resumen Financiero')
                    ->schema([
                        TextEntry::make('op_gravadas')
                            ->label('Op. Gravadas')
                            ->money('PEN'),

                        TextEntry::make('igv')
                            ->label('IGV (18%)')
                            ->money('PEN'),

                        TextEntry::make('total')
                            ->label('IMPORTE TOTAL')
                            ->money('PEN')
                            ->size(TextEntry\TextEntrySize::Large) // Letra más grande para el total
                            ->weight('bold')
                            ->color('primary'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('document_number')
                ->label('Comprobante')
                ->state(fn (Sale $record): string => "{$record->series}-{$record->correlative}")
                ->searchable(['series', 'correlative'])
                ->sortable(false)
                ->weight('bold')
                ->icon(fn (Sale $record): string => match ($record->document_type) {
                    '01' => 'heroicon-o-document-text',
                    '03' => 'heroicon-o-receipt-percent',
                    '07' => 'heroicon-o-arrow-uturn-left',
                    '08' => 'heroicon-o-arrow-trending-up',
                    default => 'heroicon-o-document',
                })
                ->description(fn (Sale $record): ?string =>
                    in_array($record->document_type, ['07', '08'])
                        ? "Ref: {$record->affected_document_series}-{$record->affected_document_correlative}"
                        : match ($record->document_type) {
                            '01' => 'Factura',
                            '03' => 'Boleta',
                            default => null,
                        }
                )
                ->color(fn (Sale $record): string => match ($record->document_type) {
                    '07' => 'danger',
                    '08' => 'warning',
                    '01' => 'info',
                    '03' => 'success',
                    default => 'gray',
                }),

            Tables\Columns\TextColumn::make('customer.name')
                ->label('Cliente')
                ->placeholder('Público en General')
                ->sortable()
                ->searchable()
                ->icon('heroicon-o-user')
                ->limit(30)
                ->tooltip(fn (Sale $record): ?string => $record->customer?->name),

            Tables\Columns\TextColumn::make('user.name')
                ->label('Cajero')
                ->icon('heroicon-o-identification')
                ->limit(20)
                ->toggleable(), // Permite ocultar la columna si la pantalla es pequeña

            Tables\Columns\TextColumn::make('total')
                ->label('Total')
                ->money('PEN')
                ->sortable()
                ->weight('bold')
                ->alignment('right')
                ->size('lg'),

            Tables\Columns\TextColumn::make('payment_method')
                ->label('Pago')
                ->badge()
                ->icon(fn (string $state): string => match ($state) {
                    'Efectivo' => 'heroicon-o-banknotes',
                    'Yape', 'Plin' => 'heroicon-o-device-phone-mobile',
                    'Tarjeta' => 'heroicon-o-credit-card',
                    'Transferencia' => 'heroicon-o-arrow-path',
                    default => 'heroicon-o-currency-dollar',
                })
                ->color(fn (string $state): string => match ($state) {
                    'Efectivo' => 'success',
                    'Yape' => 'purple',
                    'Plin' => 'info',
                    'Tarjeta' => 'warning',
                    'Transferencia' => 'gray',
                    default => 'gray',
                })
                ->toggleable(),

            Tables\Columns\TextColumn::make('sunat_status')
                ->label('SUNAT')
                ->badge()
                ->icon(fn (string $state): string => match ($state) {
                    'accepted' => 'heroicon-o-check-circle',
                    'pending' => 'heroicon-o-clock',
                    'rejected' => 'heroicon-o-x-circle',
                    default => 'heroicon-o-question-mark-circle',
                })
                ->color(fn (string $state): string => match ($state) {
                    'accepted' => 'success',
                    'pending' => 'warning',
                    'rejected' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'accepted' => 'Aceptado',
                    'pending' => 'Pendiente',
                    'rejected' => 'Rechazado',
                    default => ucfirst($state),
                })
                ->tooltip(fn (Sale $record): ?string =>
                    $record->sent_at
                        ? "Enviado: {$record->sent_at->format('d/m/Y H:i')}"
                        : $record->sunat_description
                ),

            Tables\Columns\TextColumn::make('sold_at')
                ->label('Fecha')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->icon('heroicon-o-calendar')
                ->since()
                ->tooltip(fn (Sale $record): string => $record->sold_at->format('d/m/Y H:i:s')),
        ])
        ->defaultSort('sold_at', 'desc')
        ->filters([
            Tables\Filters\SelectFilter::make('document_type')
                ->label('Tipo')
                ->options([
                    '01' => 'Facturas',
                    '03' => 'Boletas',
                    '07' => 'Notas de Crédito',
                    '08' => 'Notas de Débito',
                ])
                ->multiple(),

            Tables\Filters\SelectFilter::make('sunat_status')
                ->label('Estado SUNAT')
                ->options([
                    'accepted' => 'Aceptado',
                    'pending' => 'Pendiente',
                    'rejected' => 'Rechazado',
                ])
                ->multiple(),

            Tables\Filters\SelectFilter::make('payment_method')
                ->label('Método de Pago')
                ->options(Sale::PAYMENT_METHODS)
                ->multiple(),

            Tables\Filters\Filter::make('sold_at')
                ->form([
                    Forms\Components\DatePicker::make('desde')
                        ->label('Desde'),
                    Forms\Components\DatePicker::make('hasta')
                        ->label('Hasta'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['desde'],
                            fn (Builder $query, $date): Builder => $query->whereDate('sold_at', '>=', $date),
                        )
                        ->when(
                            $data['hasta'],
                            fn (Builder $query, $date): Builder => $query->whereDate('sold_at', '<=', $date),
                        );
                })
                ->indicateUsing(function (array $data): array {
                    $indicators = [];
                    if ($data['desde'] ?? null) {
                        $indicators[] = 'Desde: ' . \Carbon\Carbon::parse($data['desde'])->format('d/m/Y');
                    }
                    if ($data['hasta'] ?? null) {
                        $indicators[] = 'Hasta: ' . \Carbon\Carbon::parse($data['hasta'])->format('d/m/Y');
                    }
                    return $indicators;
                }),
        ])
        ->filtersFormWidth('md')
        ->filtersFormColumns(2)
        ->actions([
            // GRUPO 1: Acciones Principales (Envío y Ticket)
            Tables\Actions\Action::make('sendToSunat')
                ->label('SUNAT')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')

                // 1. ¿Se muestra el botón? (Si no ha sido aceptado)
                ->visible(fn (Sale $record) => $record->sunat_status !== 'accepted')

                // 2. ¿Está habilitado para hacer clic? (Tu código va aquí)
                // Si es Nota de Crédito y no tiene motivo, el botón se verá gris y no funcionará.
                ->disabled(fn (Sale $record) =>
                    in_array($record->document_type, ['07', '08']) && empty($record->credit_note_type)
                )
                ->requiresConfirmation()
                ->modalHeading('Enviar Comprobante')
                ->modalDescription('¿Estás seguro de enviar este documento a la SUNAT?')
                ->action(function (Sale $record) {
                    try {
                        $service = new \Percy\Core\Services\SunatService();
                        $result = $service->processAndSend($record);

                        if ($result->isSuccess()) {
                            \Filament\Notifications\Notification::make()
                                ->title('¡Aceptado por SUNAT!')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Error SUNAT ' . $result->getError()->getCode())
                                ->body($result->getError()->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    } catch (\GuzzleHttp\Exception\ConnectException $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Error de Conexión')
                            ->body('No se pudo conectar con SUNAT. Verifique su conexión a internet.')
                            ->danger()
                            ->persistent()
                            ->send();
                    } catch (\GuzzleHttp\Exception\RequestException $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Timeout')
                            ->body('La solicitud a SUNAT tardó demasiado. Inténtelo nuevamente.')
                            ->warning()
                            ->persistent()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Error Crítico')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Tables\Actions\Action::make('print')
                ->label('Ticket') // Cambié el nombre para mayor claridad
                ->icon('heroicon-o-printer')
                ->color('info')
                // Cambiamos 'sales.ticket' por la ruta del core: 'percy.print.ticket'
                ->url(fn (Sale $record): string => route('percy.print.ticket', $record))
                ->openUrlInNewTab(),

            // GRUPO 2: Archivos Digitales
            Tables\Actions\ActionGroup::make([
                // 1. Mejoramos el botón Ver para que tenga icono y color
                Tables\Actions\ViewAction::make()
                    ->label('Ver Detalle')
                    ->icon('heroicon-o-eye')
                    ->color('info'),

                //Tables\Actions\EditAction::make()->label('Editar'),

                Tables\Actions\Action::make('downloadXml')
                    ->label('Descargar XML')
                    ->icon('heroicon-o-code-bracket')
                    ->url(fn (Sale $record) => route('sales.download-xml', $record))
                    ->visible(fn (Sale $record) => !empty($record->sunat_xml_path)),

                Tables\Actions\Action::make('downloadCdr')
                    ->label('Descargar CDR')
                    ->icon('heroicon-o-archive-box')
                    ->url(fn (Sale $record) => route('sales.download-cdr', $record))
                    ->visible(fn (Sale $record) => !empty($record->sunat_cdr_path)),

                Tables\Actions\Action::make('anularVenta')
                    ->label('Nota de Credito')
                    ->icon('heroicon-o-document-minus')
                    ->color('danger')
                    // Solo se muestra si el documento original es Boleta o Factura y ya fue aceptado
                    ->visible(fn (Sale $record) => $record->sunat_status === 'accepted' && in_array($record->document_type, ['01', '03']))
                    ->form([
                        Forms\Components\Select::make('serie_nota')
                            ->label('Serie de Nota de Crédito')
                            ->options(function (Sale $record) {
                                // Determinamos el prefijo: Factura -> FC, Boleta -> BC
                                $prefix = ($record->document_type === '01') ? 'FC' : 'BC';

                                return \Percy\Core\Models\Serie::where('document_type', '07')
                                    ->where('serie', 'like', $prefix . '%')
                                    ->where('active', true)
                                    ->pluck('serie', 'serie');
                            })
                            ->required(),

                        //Forms\Components\TextInput::make('correlativo_nota')
                            //->label('Correlativo de la Nota (Ej: 1)')
                            //->numeric()
                            //->required(),

                        Forms\Components\Select::make('credit_note_type')
                            ->label('Motivo de Anulación')
                            ->options([
                                '01' => 'Anulación de la operación',
                                '02' => 'Anulación por error en el RUC',
                                '03' => 'Corrección por error en la descripción',
                                '06' => 'Devolución total',
                                '07' => 'Devolución por ítem',
                                '10' => 'Otros Conceptos',
                                ])
                            ->default('01')
                            ->required(),
                    ])
                    ->action(function (array $data, Sale $record) {
                        try {
                            // 1. Clonamos la venta original pero vaciamos los datos de respuesta SUNAT anteriores
                            // --- LÓGICA DE CORRELATIVO AUTOMÁTICO ---
                            $serieConfig = \Percy\Core\Models\Serie::where('document_type', '07')
                                ->where('serie', $data['serie_nota'])
                                ->first();

                            if (!$serieConfig) {
                                throw new \Exception("Debe registrar la serie {$data['serie_nota']} en Configuración primero.");
                            }

                            $serieConfig->increment('correlative'); // +1 al contador de tu tabla series

                            // Clonamos la venta original pero vaciamos los datos de respuesta SUNAT anteriores
                            $nota = $record->replicate([
                                'sunat_status', 'sunat_code', 'sunat_description', 'sunat_hash',
                                'sunat_xml_path', 'sunat_cdr_path', 'sunat_pdf_path', 'legend_text'
                            ]);
                            $nota->document_type = '07';
                            $nota->series = $data['serie_nota'];
                            $nota->correlative = $serieConfig->correlative; // Asignamos el número automático ✅

                            $nota->status = 'completed';

                            // 3. Vinculamos el documento original (La Boleta/Factura que estamos anulando)
                            $nota->affected_document_type = $record->document_type;
                            $nota->affected_document_series = $record->series;
                            $nota->affected_document_correlative = $record->correlative;
                            $nota->credit_note_type = $data['credit_note_type'];

                            // Definimos la descripción según el código elegido
                            $descripciones = [
                                '01' => 'Anulación de la operación',
                                '02' => 'Anulación por error en el RUC',
                                '03' => 'Corrección por error en la descripción',
                                '06' => 'Devolución total',
                                '07' => 'Devolución por ítem',
                                '10' => 'Otros Conceptos',
                            ];
                            $nota->cancel_reason_description = $descripciones[$data['credit_note_type']];

                            // Guardamos el nuevo registro padre
                            $nota->save();

                            // 4. Clonamos los ítems originales idénticos para que la contabilidad cuadre exacto
                            foreach ($record->items as $item) {
                                $nuevoItem = $item->replicate(['sale_id']);
                                $nuevoItem->sale_id = $nota->id;
                                $nuevoItem->save();
                            }

                            // 5. Enviamos la nueva Nota de Crédito a la SUNAT usando tu Service
                            $service = new \Percy\Core\Services\SunatService();
                            $result = $service->processAndSend($nota);

                            if ($result->isSuccess()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Nota de Crédito Aceptada')
                                    ->body('Se anuló el comprobante y se devolvió el stock correctamente.')
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error SUNAT ' . $result->getError()->getCode())
                                    ->body($result->getError()->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error Crítico')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('aumentarValor')
                    ->label('Nota de Débito')
                    ->icon('heroicon-o-document-plus') // Un ícono de "más"
                    ->color('warning') // Color amarillo para diferenciarlo del rojo de anulación
                    ->visible(fn (Sale $record) => $record->sunat_status === 'accepted' && in_array($record->document_type, ['01', '03']))
                    ->form([
                        Forms\Components\Select::make('serie_nota')
                            ->label('Serie de Nota de Débito (Ej: BD01 o FD01)')
                            ->options(function (Sale $record) {
                                // Factura -> FD, Boleta -> BD
                                $prefix = ($record->document_type === '01') ? 'FD' : 'BD';

                                return \Percy\Core\Models\Serie::where('document_type', '08')
                                    ->where('serie', 'like', $prefix . '%')
                                    ->where('active', true)
                                    ->pluck('serie', 'serie');
                            })
                            ->required(),

                        //Forms\Components\TextInput::make('correlativo_nota')
                            //->label('Correlativo de la Nota (Ej: 1)')
                            //->numeric()
                            //->required(),

                        Forms\Components\Select::make('debit_note_type')
                            ->label('Motivo de Débito (SUNAT)')
                            ->options([
                                '01' => 'Intereses por mora',
                                '02' => 'Aumento en el valor',
                                '03' => 'Penalidades/otros conceptos',
                            ])
                            ->default('02')
                            ->required(),

                        // NUEVO: Pedimos el producto (para que cuadre en tu BD) y el monto a cobrar
                        Forms\Components\Select::make('product_id')
                            ->label('Concepto a cobrar')
                            ->options(\Percy\Core\Models\Product::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('importe_adicional')
                            ->label('Importe a Sumar (Inc. IGV)')
                            ->numeric()
                            ->required()
                            ->prefix('S/'),
                    ])
                    ->action(function (array $data, Sale $record) {
                        try {
                            // --- LÓGICA DE CORRELATIVO AUTOMÁTICO ---
                            $serieConfig = \Percy\Core\Models\Serie::where('document_type', '08')
                                ->where('serie', $data['serie_nota'])
                                ->first();

                            if (!$serieConfig) {
                                throw new \Exception("Debe registrar la serie {$data['serie_nota']} en Configuración primero.");
                            }

                            $serieConfig->increment('correlative');

                            // Clonamos la venta original limpia de estados
                            $nota = $record->replicate([
                                'sunat_status',
                                'sunat_code',
                                'sunat_description',
                                'sunat_hash',
                                'sunat_xml_path',
                                'sunat_cdr_path',
                                'sunat_pdf_path',
                                'legend_text'
                            ]);
                            $nota->document_type = '08';
                            $nota->series = $data['serie_nota'];
                            $nota->correlative = $serieConfig->correlative; // Número automático ✅

                            $nota->status = 'completed';

                            // 3. Vinculamos el documento original
                            $nota->affected_document_type = $record->document_type;
                            $nota->affected_document_series = $record->series;
                            $nota->affected_document_correlative = $record->correlative;
                            $nota->credit_note_type = $data['debit_note_type']; // Usamos la misma columna de BD

                            // Definimos la descripción según el Catálogo 10
                            $descripciones = [
                                '01' => 'Intereses por mora',
                                '02' => 'Aumento en el valor',
                                '03' => 'Penalidades/otros conceptos'
                            ];
                            $nota->cancel_reason_description = $descripciones[$data['debit_note_type']];

                            // NUEVA MATEMÁTICA: Calculamos todo en base al nuevo importe
                            $total = (float) $data['importe_adicional'];
                            $base = $total / 1.18; // Asumiendo que es gravado
                            $igv = $total - $base;

                            $nota->op_gravadas = round($base, 2);
                            $nota->igv = round($igv, 2);
                            $nota->total = round($total, 2);
                            $nota->op_exoneradas = 0;
                            $nota->op_inafectas = 0;

                            $nota->save();

                            // En lugar de clonar todos los ítems, creamos UNO SOLO con el cargo extra
                            $producto = \Percy\Core\Models\Product::find($data['product_id']);

                            $nota->items()->create([
                                'product_id' => $producto->id,
                                'item_name' => $producto->name . ' - ' . $nota->cancel_reason_description,
                                'quantity' => 1,
                                'unit_price' => round($total, 2),
                                'unit_value' => round($base, 2),
                                'igv_amount' => round($igv, 2),
                                'total' => round($total, 2),
                                'afectacion_igv_id' => $producto->afectacion_igv_id ?? 1,
                            ]);

                            // 5. Enviamos la Nota de Débito
                            $service = new \Percy\Core\Services\SunatService();
                            $result = $service->processAndSend($nota);

                            if ($result->isSuccess()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Nota de Débito Aceptada')
                                    ->body('Se generó el comprobante correctamente.')
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error SUNAT ' . $result->getError()->getCode())
                                    ->body($result->getError()->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error Crítico')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

            ])
            ->label('Opciones')
            ->icon('heroicon-m-ellipsis-vertical')
            ->button()
            ->color('gray'),

        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
    }

    // =========================================================================
    // MÉTODOS DE CÁLCULO MATEMÁTICO (Facturación SUNAT)
    // =========================================================================

    public static function updateRow(Get $get, Set $set): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $afectacionId = $get('afectacion_igv_id') ?? 1;

        $afectacion = AfectacionIgv::find($afectacionId);
        $porcentaje = ($afectacion && $afectacion->gravado) ? ($afectacion->porcentaje / 100) : 0;

        $rowTotal = $quantity * $unitPrice;
        $unitValue = $unitPrice / (1 + $porcentaje);
        $igvAmount = ($unitPrice - $unitValue) * $quantity;

        // Guarda en los campos de la fila (incluso los ocultos)
        $set('unit_value', round($unitValue, 2));
        $set('igv_amount', round($igvAmount, 2));
        $set('total', round($rowTotal, 2));
    }

    /**
     * Recorre todas las filas y suma los totales globales para el panel derecho
     */
    public static function updateTotals(Get $get, Set $set): void
    {
        // TRUCO AVANZADO: Detecta si estamos dentro del Repetidor o fuera de él.
        $items = $get('items');
        if ($items === null) {
            // Si es null, significa que estamos dentro de una fila. ¡Subimos un nivel!
            $items = $get('../../items') ?? [];
            $prefix = '../../'; // Usamos este prefijo para apuntar al panel derecho
        } else {
            // Si no es null, estamos en la raíz del formulario
            $prefix = '';
        }

        $op_gravadas = 0;
        $op_exoneradas = 0;
        $op_inafectas = 0;
        $igv = 0;
        $totalGeneral = 0;

        foreach ($items as $item) {
            // Recalculamos al vuelo para tener la matemática 100% fresca
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $afecId = $item['afectacion_igv_id'] ?? 1;

            $afectacion = AfectacionIgv::find($afecId);
            $porcentaje = ($afectacion && $afectacion->gravado) ? ($afectacion->porcentaje / 100) : 0;

            $rowTotal = $qty * $price;

            if ($afectacion && $afectacion->gravado) {
                $base = $rowTotal / (1 + $porcentaje);
                $op_gravadas += $base;
                $igv += ($rowTotal - $base);
            } elseif ($afectacion && str_starts_with($afectacion->codigo, '2')) {
                $op_exoneradas += $rowTotal;
            } elseif ($afectacion && str_starts_with($afectacion->codigo, '3')) {
                $op_inafectas += $rowTotal;
            }

            $totalGeneral += $rowTotal;
        }

        // Actualizamos el panel derecho inyectando los datos con su prefijo
        $set($prefix . 'op_gravadas', round($op_gravadas, 2));
        $set($prefix . 'op_exoneradas', round($op_exoneradas, 2));
        $set($prefix . 'op_inafectas', round($op_inafectas, 2));
        $set($prefix . 'igv', round($igv, 2));
        $set($prefix . 'total', round($totalGeneral, 2));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'view' => Pages\ViewSale::route('/{record}'), // NUEVA RUTA DE LECTURA
            //'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
