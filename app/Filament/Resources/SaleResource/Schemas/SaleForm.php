<?php

namespace App\Filament\Resources\SaleResource\Schemas;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Filament\Resources\SaleResource\Support\SaleFormCalculations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Sale;
use Percy\Core\Models\Product;
use Percy\Core\Services\Tenants\TenantFeatureService;
use Percy\Core\Services\Tenants\TenantPricingService;
use Percy\Core\Services\Inventory\ProductBatchService;
use Percy\Core\Services\Tenants\TenantPlanService;

class SaleForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                // 🌟 1. AVISO PARA EL CAJERO (Solo visible en ventas web pendientes)
                Forms\Components\Section::make('🛍️ Pedido web pendiente de atención')
                    ->description('Verifica los datos del cliente, confirma el pago y selecciona el comprobante antes de procesar el pedido. Al guardar, el pedido pasará a venta completada, se descontará stock y se registrará el Kardex.')
                    ->schema([
                        Forms\Components\Placeholder::make('web_customer_name')
                            ->label('Cliente')
                            ->content(fn(?Sale $record): string => self::extractWebNote($record, 'Nombre') ?? 'No registrado'),

                        Forms\Components\Placeholder::make('web_customer_phone')
                            ->label('Celular / WhatsApp')
                            ->content(fn(?Sale $record): string => self::extractWebNote($record, 'Celular') ?? 'No registrado'),

                        Forms\Components\Placeholder::make('web_delivery_type')
                            ->label('Tipo de entrega')
                            ->content(function (?Sale $record): string {
                                if (! $record?->kitchen_notes) {
                                    return 'No registrado';
                                }

                                return str_contains($record->kitchen_notes, 'RECOJO EN TIENDA')
                                    ? 'Recojo en tienda'
                                    : 'Delivery';
                            }),

                        Forms\Components\Placeholder::make('web_district')
                            ->label('Distrito')
                            ->content(fn(?Sale $record): string => self::extractWebNote($record, 'Distrito') ?? '-')
                            ->visible(fn(?Sale $record): bool => $record && str_contains($record->kitchen_notes ?? '', 'Distrito:')),

                        Forms\Components\Placeholder::make('web_address')
                            ->label('Dirección')
                            ->content(fn(?Sale $record): string => self::extractWebNote($record, 'Dirección') ?? '-')
                            ->columnSpanFull()
                            ->visible(fn(?Sale $record): bool => $record && str_contains($record->kitchen_notes ?? '', 'Dirección:')),

                        Forms\Components\Placeholder::make('web_notes')
                            ->label('Notas del cliente')
                            ->content(fn(?Sale $record): string => self::extractWebNote($record, 'Notas') ?? 'Sin notas adicionales')
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->extraAttributes(['class' => 'ring-2 ring-amber-500 bg-amber-50/40 rounded-xl'])
                    ->visible(fn(?Sale $record) => $record && $record->channel === 'ecommerce' && $record->status === 'pending_payment'),

                Forms\Components\Group::make()->schema([
                    // 🌟 1. INFORMACIÓN DE VENTA (Responsivo)
                    Forms\Components\Section::make('Información de Venta')
                        ->icon('heroicon-o-document-text')
                        ->description('Define el comprobante, cliente y forma de pago.')
                        ->schema([
                            Forms\Components\Select::make('document_type')
                                ->label('Tipo de Comprobante')
                                ->options(fn(?Sale $record): array => self::documentTypeOptions($record))
                                ->required()
                                ->default('00')
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $serieAuto = \Percy\Core\Models\Serie::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                                        ->where('document_type', $state)
                                        ->where('active', true)
                                        ->value('serie');
                                    $set('series', $serieAuto);
                                })
                                ->disabled(fn(?Sale $record) => $record && $record->status !== 'pending_payment')
                                ->columnSpan(['default' => 1, 'md' => 1]),

                            Forms\Components\Select::make('series')
                                ->label('Serie')
                                ->required()
                                ->options(function (Get $get) {
                                    $docType = $get('document_type');
                                    if (!$docType) return [];
                                    return \Percy\Core\Models\Serie::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                                        ->where('document_type', $docType)
                                        ->where('active', true)
                                        ->pluck('serie', 'serie');
                                })
                                // CÓDIGO CORREGIDO (DINÁMICO):
                                ->default(function (Get $get) {
                                    // 🌟 Ahora lee el documento que esté seleccionado arriba, o usa '00' si no hay nada
                                    $docType = $get('document_type') ?? '00';

                                    return \Percy\Core\Models\Serie::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                                        ->where('document_type', $docType)
                                        ->where('active', true)
                                        ->value('serie');
                                })
                                ->selectablePlaceholder(false)
                                ->disabled(fn(?Sale $record) => $record && $record->status !== 'pending_payment')
                                ->columnSpan(['default' => 1, 'md' => 1]),

                            Forms\Components\Hidden::make('correlative'),

                            Forms\Components\Select::make('customer_id')
                                ->label('Cliente')
                                ->relationship('customer', 'name', function (Builder $query, \Filament\Forms\Get $get) {
                                    $docType = $get('document_type');
                                    return $query->when($docType === '01', function ($q) {
                                        return $q->whereIn('document_type', ['RUC', '6']);
                                    })->when(in_array($docType, ['03', '00']), function ($q) {
                                        return $q->whereIn('document_type', ['DNI', '1', 'CE', '0', '7', '4']);
                                    });
                                })
                                ->searchable(['name', 'document_number'])
                                ->preload()
                                ->live()
                                ->required(fn(\Filament\Forms\Get $get) => $get('document_type') === '01')
                                ->hint(fn(\Filament\Forms\Get $get): string => $get('document_type') === '01' ? 'Obligatorio' : 'Opcional')
                                ->hintIcon(
                                    'heroicon-m-question-mark-circle',
                                    tooltip: fn(\Filament\Forms\Get $get): string =>
                                    $get('document_type') === '01'
                                        ? 'Para facturas debes seleccionar un cliente con RUC.'
                                        : 'Puedes dejarlo vacío para registrar la venta como Consumidor Final.'
                                )
                                ->disabled(fn(?Sale $record) => $record && $record->status !== 'pending_payment')
                                // 🌟 Ocupará 2 columnas en PC, pero 1 en móvil
                                ->columnSpan(['default' => 1, 'md' => 2])
                                ->createOptionAction(
                                    fn(\Filament\Forms\Components\Actions\Action $action) => $action
                                        ->icon('heroicon-s-user-plus')
                                        ->color('primary')
                                        ->tooltip('Registrar Nuevo Cliente')
                                        ->mutateFormDataUsing(function (array $data) {
                                            $data['tenant_id'] = \Illuminate\Support\Facades\Auth::user()->tenant_id;
                                            return $data;
                                        })
                                        ->modalHeading('Registrar Nuevo Cliente')
                                        ->modalWidth('2xl')
                                )
                                ->createOptionForm([
                                    Forms\Components\Section::make('Identidad del Cliente')
                                        ->schema([
                                            Forms\Components\Select::make('document_type')
                                                ->label('Tipo de Documento')
                                                ->options([
                                                    'DNI' => 'DNI',
                                                    'RUC' => 'RUC',
                                                    'CE' => 'Carné de Extranjería',
                                                ])
                                                ->default('DNI')
                                                ->required()
                                                ->native(false)
                                                ->live()
                                                ->columnSpan(1),

                                            Forms\Components\TextInput::make('document_number')
                                                ->label('Número')
                                                ->maxLength(fn(\Filament\Forms\Get $get) => match ($get('document_type')) {
                                                    'DNI' => 8,
                                                    'RUC' => 11,
                                                    default => 15,
                                                })
                                                ->minLength(fn(\Filament\Forms\Get $get) => match ($get('document_type')) {
                                                    'DNI' => 8,
                                                    'RUC' => 11,
                                                    default => null,
                                                })
                                                ->numeric(fn(\Filament\Forms\Get $get) => in_array($get('document_type'), ['DNI', 'RUC']))
                                                ->placeholder(fn(\Filament\Forms\Get $get) => $get('document_type') === 'RUC' ? 'Ej: 20... (11 dígitos)' : 'Ej: 12345678')
                                                ->required()
                                                ->columnSpan(1)
                                                ->suffixAction(
                                                    \Filament\Forms\Components\Actions\Action::make('searchDecolecta')
                                                        ->icon('heroicon-m-magnifying-glass')
                                                        ->color('primary')
                                                        ->tooltip('Buscar RUC (Decolecta)')
                                                        ->visible(fn(\Filament\Forms\Get $get) => $get('document_type') === 'RUC')
                                                        ->action(function ($state, \Filament\Forms\Set $set) {
                                                            if (blank($state) || strlen($state) !== 11) {
                                                                \Filament\Notifications\Notification::make()->danger()->title('Error')->body('Ingrese un RUC válido de 11 dígitos.')->send();
                                                                return;
                                                            }
                                                            $token = config('services.decolecta.token');
                                                            try {
                                                                $response = \Illuminate\Support\Facades\Http::withToken($token)->timeout(10)->get("https://api.decolecta.com/v1/sunat/ruc?numero={$state}");
                                                                if ($response->successful()) {
                                                                    $data = $response->json();
                                                                    if (($data['estado'] ?? '') !== 'ACTIVO') {
                                                                        \Filament\Notifications\Notification::make()->warning()->title('Cuidado')->body('Este RUC figura como ' . ($data['estado'] ?? 'INACTIVO') . ' en SUNAT.')->send();
                                                                    } else {
                                                                        \Filament\Notifications\Notification::make()->success()->title('RUC Encontrado')->send();
                                                                    }
                                                                    $set('name', $data['razon_social'] ?? '');
                                                                    $dir = trim($data['direccion'] ?? '');
                                                                    $dep = trim($data['departamento'] ?? '');
                                                                    $prov = trim($data['provincia'] ?? '');
                                                                    $dist = trim($data['distrito'] ?? '');
                                                                    $fullAddress = trim("$dir $dep - $prov - $dist", " -");
                                                                    $fullAddress = preg_replace('/\s+/', ' ', $fullAddress);
                                                                    $set('address', $fullAddress);
                                                                } else {
                                                                    \Filament\Notifications\Notification::make()->danger()->title('No encontrado')->body('El RUC no existe en SUNAT o superó el límite.')->send();
                                                                }
                                                            } catch (\Exception $e) {
                                                                \Filament\Notifications\Notification::make()->danger()->title('Error de conexión')->body('No se pudo conectar con la API de Decolecta.')->send();
                                                            }
                                                        })
                                                ),

                                            Forms\Components\TextInput::make('name')
                                                ->label('Nombre Completo o Razón Social')
                                                ->required()
                                                ->maxLength(150)
                                                ->columnSpanFull(),
                                        ])->columns(['default' => 1, 'sm' => 2]),

                                    Forms\Components\Section::make('Datos de Contacto')
                                        ->schema([
                                            Forms\Components\TextInput::make('phone')
                                                ->label('Teléfono')
                                                ->tel()
                                                ->maxLength(30)
                                                ->prefixIcon('heroicon-o-phone')
                                                ->columnSpan(1),

                                            Forms\Components\TextInput::make('email')
                                                ->label('Correo Electrónico')
                                                ->email()
                                                ->maxLength(150)
                                                ->prefixIcon('heroicon-o-envelope')
                                                ->columnSpan(1),

                                            Forms\Components\Textarea::make('address')
                                                ->label('Dirección Fija')
                                                ->maxLength(255)
                                                ->rows(2)
                                                ->columnSpanFull(),
                                        ])->columns(['default' => 1, 'sm' => 2])->collapsible(),
                                ]),

                            Forms\Components\Select::make('payment_method')
                                ->label('Método de Pago')
                                ->options(\Percy\Core\Models\Sale::PAYMENT_METHODS)
                                ->default('Efectivo')
                                ->live()
                                ->required()
                                ->afterStateUpdated(fn(Forms\Set $set) => $set('payment_reference', null))
                                ->disabled(fn(?Sale $record) => $record && $record->status !== 'pending_payment')
                                ->columnSpan(['default' => 1, 'md' => 1]),

                            Forms\Components\TextInput::make('payment_reference')
                                ->label('N° Operación / Ref.')
                                ->placeholder('Ej: 123456')
                                ->visible(fn(\Filament\Forms\Get $get) => \Percy\Core\Models\Sale::requiresReference($get('payment_method') ?? ''))
                                ->required(fn(\Filament\Forms\Get $get) => \Percy\Core\Models\Sale::requiresReference($get('payment_method') ?? ''))
                                ->disabled(fn(?Sale $record) => $record && $record->status !== 'pending_payment')
                                ->columnSpan(['default' => 1, 'md' => 1]),

                            Forms\Components\DateTimePicker::make('sold_at')
                                ->label('Fecha de Emisión')
                                ->default(now())
                                ->minDate(now()->subDays(7))
                                ->maxDate(now())
                                ->hintIcon(
                                    'heroicon-m-question-mark-circle',
                                    tooltip: fn(): string =>
                                    self::canUseElectronicDocuments()
                                        ? 'SUNAT solo acepta documentos de los últimos 7 días.'
                                        : 'Fecha de registro interno de la venta.'
                                )
                                ->required()
                                ->disabled(fn(?Sale $record) => $record && $record->status !== 'pending_payment')
                                ->columnSpan(['default' => 1, 'md' => 1]),
                            // 🌟 SOLUCIÓN: Agregamos estos campos como Hidden para que Filament permita guardarlos
                            //Forms\Components\Hidden::make('status'),
                            //Forms\Components\Hidden::make('user_id'),
                            //Forms\Components\Hidden::make('sunat_status'),
                            // (El de 'correlative' ya lo tenías oculto arriba, así que ese está bien)

                            Forms\Components\TextInput::make('prescription_code')
                                ->label('N° Receta / CMP')
                                ->placeholder('Ej: CMP 12345')
                                ->maxLength(255)
                                ->visible(fn(Forms\Get $get) => self::requiresPrescriptionForSelectedItems($get))
                                ->required(fn(Forms\Get $get) => self::requiresPrescriptionForSelectedItems($get))
                                ->columnSpan(['default' => 1, 'md' => 1]),

                            Forms\Components\Select::make('status')
                                ->label('Estado')
                                ->options(['completed' => 'Completado', 'pending_payment' => 'Pendiente de Cobro', 'canceled' => 'Anulado'])
                                ->default('completed')
                                ->hidden(),
                        ])->columns(['default' => 1, 'md' => 4]), // 🌟 El contenedor usa 1 col en móvil, 4 en 
                ])->columnSpan(['default' => 1, 'lg' => 3]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Resumen Financiero')
                        ->icon('heroicon-o-banknotes')
                        ->description('Calculados de la venta.')
                        ->extraAttributes(['class' => 'lg:sticky lg:top-20'])
                        ->schema([
                            Forms\Components\Placeholder::make('op_gravadas_lbl')
                                ->label('Op. Gravadas')
                                ->content(fn(Get $get): string => 'S/ ' . number_format((float)($get('op_gravadas') ?? 0), 2))
                                ->extraAttributes(['class' => 'flex justify-between border-b pb-1']),

                            Forms\Components\Placeholder::make('op_exoneradas_lbl')
                                ->label('Op. Exoneradas')
                                ->content(fn(Get $get): string => 'S/ ' . number_format((float)($get('op_exoneradas') ?? 0), 2))
                                ->extraAttributes(['class' => 'flex justify-between border-b pb-1 text-gray-500'])
                                ->visible(fn(Get $get): bool => (float)($get('op_exoneradas') ?? 0) > 0),

                            Forms\Components\Placeholder::make('op_inafectas_lbl')
                                ->label('Op. Inafectas')
                                ->content(fn(Get $get): string => 'S/ ' . number_format((float)($get('op_inafectas') ?? 0), 2))
                                ->extraAttributes(['class' => 'flex justify-between border-b pb-1 text-gray-500'])
                                ->visible(fn(Get $get): bool => (float)($get('op_inafectas') ?? 0) > 0),

                            Forms\Components\Placeholder::make('subtotal_lbl')
                                ->label('SUBTOTAL')
                                ->content(function (Get $get) {
                                    $sub = (float)($get('op_gravadas') ?? 0) + (float)($get('op_exoneradas') ?? 0) + (float)($get('op_inafectas') ?? 0);
                                    return 'S/ ' . number_format($sub, 2);
                                })
                                ->extraAttributes(['class' => 'flex justify-between border-b pb-1 font-semibold text-gray-700 dark:text-gray-300']),

                            Forms\Components\Placeholder::make('igv_lbl')
                                ->label(function () {
                                    // 🌟 Leemos el IGV del negocio logueado dinámicamente
                                    $igv = \Illuminate\Support\Facades\Auth::user()->tenant->igv_percentage ?? 18;
                                    return "IGV ({$igv}%)";
                                })
                                ->content(fn(Get $get): string => 'S/ ' . number_format((float)($get('igv') ?? 0), 2))
                                ->extraAttributes(['class' => 'flex justify-between border-b pb-1']),

                            Forms\Components\Placeholder::make('total_lbl')
                                ->label('IMPORTE TOTAL')
                                ->content(fn(Get $get): string => 'S/ ' . number_format((float)($get('total') ?? 0), 2))
                                ->extraAttributes(['class' => 'flex justify-between text-2xl font-black text-primary-600 pt-2']),

                            Forms\Components\Hidden::make('op_gravadas'),
                            Forms\Components\Hidden::make('op_exoneradas'),
                            Forms\Components\Hidden::make('op_inafectas'),
                            Forms\Components\Hidden::make('igv'),
                            Forms\Components\Hidden::make('total'),
                        ]),
                ])->columnSpan(['default' => 1, 'lg' => 1]),

                // 🌟 2. DETALLE DE PRODUCTOS
                Forms\Components\Section::make('Detalle de Productos')
                    ->icon('heroicon-o-shopping-bag')
                    ->description('Agrega productos manualmente o usa el lector de código de barras.')
                    ->schema([
                        Forms\Components\TextInput::make('scanner')
                            ->label('Lector de Código de Barras')
                            ->placeholder('Dispare la pistola aquí...')
                            ->prefixIcon('heroicon-o-qr-code')
                            ->autofocus()
                            ->extraInputAttributes(['x-on:keydown.enter.prevent' => '$wire.$refresh()'])
                            ->live(debounce: 250)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (empty($state)) return;
                                $product = \Percy\Core\Models\Product::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                                    ->where('barcode', $state)->first();

                                if ($product) {
                                    $items = $get('items') ?? [];
                                    $batch = app(TenantFeatureService::class)->has('has_lots')
                                        ? app(ProductBatchService::class)->nextAvailableForProduct(
                                            $product,
                                            Auth::user()->tenant_id
                                        )
                                        : null;

                                    $priceData = app(TenantPricingService::class)->priceDataForProduct($product);

                                    $precioVentaFinal = $priceData['box_price'];
                                    $precioFraccionFinal = $priceData['fraction_price'];
                                    $unitValue = $priceData['unit_value'];
                                    $igvAmount = $priceData['igv_amount'];

                                    $igvAmount = $precioVentaFinal - $unitValue;

                                    $items[(string) \Illuminate\Support\Str::uuid()] = [
                                        'product_id' => $product->id,
                                        'product_batch_id' => $batch ? $batch->id : null,
                                        'measurement_unit' => 'box',
                                        'quantity' => 1,
                                        'unit_price' => round($precioVentaFinal, 2), // 🌟 Ahora va el precio final calculado
                                        'total' => round($precioVentaFinal, 2),
                                        'afectacion_igv_id' => $product->afectacion_igv_id,
                                        'unit_code' => $product->unidadSunat ? $product->unidadSunat->codigo : 'NIU',
                                        '_stock_disponible' => $product->current_stock,
                                        '_is_fractionable' => $product->is_fractionable,
                                        '_box_price' => round($precioVentaFinal, 2),
                                        '_fraction_price' => round($precioFraccionFinal, 2), // 🌟 Fracción también protegida
                                        '_is_weighable' => $product->is_weighable,
                                        'item_name' => $product->name,
                                        'unit_value' => round($unitValue, 2),
                                        'igv_amount' => round($igvAmount, 2),
                                    ];

                                    $set('items', $items);
                                    SaleFormCalculations::updateTotals($get, $set);
                                    \Filament\Notifications\Notification::make()->title('Agregado: ' . $product->name)->success()->send();
                                } else {
                                    \Filament\Notifications\Notification::make()->title('Código no encontrado')->danger()->send();
                                }
                                $set('scanner', null);
                            })
                            ->hidden(fn(?Sale $record) => $record && $record->channel === 'ecommerce')
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                SaleFormCalculations::updateTotals($get, $set);
                            })
                            ->deleteAction(
                                fn(\Filament\Forms\Components\Actions\Action $action) => $action->after(fn(Get $get, Set $set) => SaleFormCalculations::updateTotals($get, $set))
                            )
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->label('Producto')
                                    ->searchable()
                                    ->preload()
                                    // ❌ ELIMINAMOS ->required()
                                    ->required(false) // 🌟 Ahora es opcional (para permitir el delivery)
                                    // 🌟 REPEATER RESPONSIVO: Ocupa 1 col en móvil y 4 en PC
                                    ->columnSpan(['default' => 1, 'md' => 12, '2xl' => 4])
                                    ->live(onBlur: true)
                                    // 🌟 Ocultamos este select SI no hay un product_id guardado previamente
                                    // (Asi el cajero solo ve el texto "Servicio de Delivery")
                                    ->hidden(
                                        fn(\Filament\Forms\Get $get) =>
                                        $get('../../channel') === 'ecommerce' &&
                                            empty($get('product_id')) &&
                                            !empty($get('item_name'))
                                    )
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $product = \Percy\Core\Models\Product::query()
                                                ->where('tenant_id', Auth::user()->tenant_id)
                                                ->find($state);

                                            if (!$product) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Producto no disponible')
                                                    ->body('El producto seleccionado no pertenece a este negocio o no existe.')
                                                    ->danger()
                                                    ->send();

                                                return;
                                            }

                                            $priceData = app(TenantPricingService::class)->priceDataForProduct($product);

                                            $set('unit_price', $priceData['box_price']);
                                            $set('_box_price', $priceData['box_price']);
                                            $set('_fraction_price', $priceData['fraction_price']);

                                            // Estos quedan igual
                                            $set('afectacion_igv_id', $product->afectacion_igv_id);
                                            $set('unit_code', $product->unidadSunat ? $product->unidadSunat->codigo : 'NIU');
                                            $set('item_name', $product->name);
                                            $set('_stock_disponible', $product->current_stock);
                                            $set('_is_fractionable', $product->is_fractionable);
                                            $set('measurement_unit', 'box');
                                            $set('_is_weighable', $product->is_weighable);

                                            $loteProximo = app(TenantFeatureService::class)->has('has_lots')
                                                ? app(ProductBatchService::class)->nextAvailableForProduct(
                                                    (int) $state,
                                                    Auth::user()->tenant_id
                                                )
                                                : null;

                                            $set('product_batch_id', $loteProximo ? $loteProximo->id : null);
                                        }
                                        SaleFormCalculations::updateRow($get, $set);
                                        SaleFormCalculations::updateTotals($get, $set);
                                    }),

                                // 🌟 NUEVO CAMPO: Solo se muestra para el Servicio de Delivery
                                Forms\Components\Placeholder::make('item_name_preview')
                                    ->label('Servicio adicional')
                                    ->content(fn(\Filament\Forms\Get $get): string => $get('item_name') ?: '-')
                                    ->columnSpan(['default' => 1, 'md' => 12, '2xl' => 4])
                                    ->visible(fn(\Filament\Forms\Get $get) => empty($get('product_id')) && !empty($get('item_name'))),

                                Forms\Components\Select::make('measurement_unit')
                                    ->label('Presentación')
                                    ->options(['box' => 'Caja', 'unit' => 'Unidad'])
                                    ->visible(fn(Get $get) => $get('_is_fractionable'))
                                    ->required(fn(Get $get) => $get('_is_fractionable'))
                                    ->columnSpan(['default' => 1, 'md' => 2, '2xl' => 2])
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state === 'unit') {
                                            $set('unit_price', $get('_fraction_price'));
                                        } else {
                                            $set('unit_price', $get('_box_price'));
                                        }
                                        SaleFormCalculations::updateRow($get, $set);
                                        SaleFormCalculations::updateTotals($get, $set);
                                    }),

                                Forms\Components\Select::make('product_batch_id')
                                    ->label('Lote')
                                    ->options(function (Get $get) {
                                        return app(ProductBatchService::class)
                                            ->availableOptionsForProduct(
                                                $get('product_id'),
                                                Auth::user()->tenant_id
                                            );
                                    })
                                    ->visible(fn() => app(TenantFeatureService::class)->has('has_lots'))
                                    ->required(fn() => app(TenantFeatureService::class)->has('has_lots'))
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(['default' => 1, 'md' => 4, '2xl' => 4]),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(fn(\Filament\Forms\Get $get) => $get('_is_weighable') ? 0.001 : 1)
                                    ->step(fn(\Filament\Forms\Get $get) => $get('_is_weighable') ? 0.001 : 1)
                                    ->suffix(function (\Filament\Forms\Get $get) {
                                        if (!$get('_is_weighable')) return 'Und';
                                        return match ($get('unit_code')) {
                                            'KGM' => 'Kg',
                                            'LTR' => 'Lt',
                                            'GLL' => 'Gal',
                                            default => $get('unit_code') ?? 'Und',
                                        };
                                    })
                                    ->rules([
                                        fn(\Filament\Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            if (!$get('_is_weighable') && fmod((float)$value, 1) !== 0.0) {
                                                $fail('Este producto solo admite cantidades enteras.');
                                            }
                                        },
                                    ])
                                    ->maxValue(function (\Filament\Forms\Get $get) {
                                        $stock = null;

                                        if ($batchId = $get('product_batch_id')) {
                                            $batch = \Percy\Core\Models\ProductBatch::query()
                                                ->where('tenant_id', Auth::user()->tenant_id)
                                                ->where('product_id', $get('product_id'))
                                                ->find($batchId);

                                            $stock = $batch ? $batch->current_quantity : null;
                                        } elseif ($productId = $get('product_id')) {
                                            $product = \Percy\Core\Models\Product::query()
                                                ->where('tenant_id', Auth::user()->tenant_id)
                                                ->find($productId);

                                            $stock = $product ? $product->current_stock : null;
                                        }

                                        if ($stock === null) {
                                            return null;
                                        }

                                        if ($get('measurement_unit') === 'unit') {
                                            return 99999;
                                        }

                                        return $stock;
                                    })
                                    ->validationMessages([
                                        'max' => 'Stock insuficiente. La cantidad ingresada supera el stock disponible.',
                                        'max.numeric' => 'Stock insuficiente. La cantidad ingresada supera el stock disponible.',
                                        'min' => 'La cantidad debe ser mayor a cero.',
                                        'numeric' => 'La cantidad debe ser un número válido.',
                                        'required' => 'Debes ingresar una cantidad.',
                                    ])
                                    ->required()
                                    ->columnSpan(['default' => 1, 'md' => 2, '2xl' => 2])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(\Filament\Forms\Get $get, \Filament\Forms\Set $set) => [SaleFormCalculations::updateRow($get, $set), SaleFormCalculations::updateTotals($get, $set)])
                                    ->hintIcon(
                                        'heroicon-m-information-circle',
                                        tooltip: fn(\Filament\Forms\Get $get): string =>
                                        self::stockSummaryText($get) ?? 'Selecciona un producto para ver el stock disponible.'
                                    ),

                                Forms\Components\Grid::make([
                                    'default' => 1,
                                    'sm' => 2,
                                ])
                                    ->schema([
                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Precio Unitario')
                                            ->numeric()
                                            ->required()
                                            ->columnSpan(1)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn(Get $get, Set $set) => [SaleFormCalculations::updateRow($get, $set), SaleFormCalculations::updateTotals($get, $set)]),

                                        Forms\Components\TextInput::make('total')
                                            ->label('Subtotal')
                                            ->numeric()
                                            ->required()
                                            ->readonly()
                                            ->columnSpan(1),
                                    ])
                                    ->columnSpan(['default' => 1, 'md' => 4, '2xl' => 4]), // 🌟 El Grid de precio ocupará las últimas 2 columnas

                                Forms\Components\Hidden::make('_stock_disponible'),
                                Forms\Components\Hidden::make('item_name'),
                                Forms\Components\Hidden::make('unit_value'),
                                Forms\Components\Hidden::make('afectacion_igv_id'),
                                Forms\Components\Hidden::make('igv_amount'),
                                Forms\Components\Hidden::make('unit_code')->default('NIU'),
                                Forms\Components\Hidden::make('_is_fractionable'),
                                Forms\Components\Hidden::make('_box_price'),
                                Forms\Components\Hidden::make('_fraction_price'),
                                Forms\Components\Hidden::make('_is_weighable')->default(false),
                            ])
                            // 👇 Agregamos esto al Repeater principal:
                            ->disabled(fn(?Sale $record) => $record && $record->channel === 'ecommerce')
                            ->columns(['default' => 1, 'md' => 12])
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->itemLabel(fn(array $state): ?string => $state['item_name'] ?? 'Producto sin seleccionar')
                            ->addActionLabel('Agregar otro producto'),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(['default' => 1, 'lg' => 4]);
    }

    private static function tenantPlanHas(string $feature): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            return false;
        }

        return app(TenantPlanService::class)->has($feature, $user->tenant);
    }

    private static function canUseElectronicDocuments(): bool
    {
        return self::tenantPlanHas('has_invoices') || self::tenantPlanHas('has_sunat');
    }

    private static function documentTypeOptions(?Sale $record = null): array
    {
        $options = [
            '00' => 'Nota de Venta (Interno)',
        ];

        /*
        * Si el plan tiene facturación electrónica, mostramos boleta y factura.
        * Si se está editando una venta antigua que ya era boleta/factura,
        * también las mostramos para no romper registros existentes.
        */
        $recordUsesElectronicDocument = $record
            && in_array($record->document_type, ['01', '03'], true);

        if (self::canUseElectronicDocuments() || $recordUsesElectronicDocument) {
            return [
                '03' => 'Boleta Electrónica',
                '01' => 'Factura Electrónica',
                '00' => 'Nota de Venta (Interno)',
            ];
        }

        return $options;
    }

    private static function requiresPrescriptionForSelectedItems(Get $get): bool
    {
        if (!app(TenantFeatureService::class)->hasAny(['has_recipes', 'has_lots'])) {
            return false;
        }

        $items = $get('items') ?? [];

        foreach ($items as $item) {
            if (empty($item['product_id'])) {
                continue;
            }

            $product = Product::find($item['product_id']);

            if ($product && $product->requires_prescription) {
                return true;
            }
        }

        return false;
    }

    private static function stockSummaryText(Get $get): ?string
    {
        if (!$get('product_id')) {
            return null;
        }

        $traducirStock = function ($stockDecimal, $isFractionable, $isWeighable, $unitCode, $unitsPerBox) {
            $stock = (float) $stockDecimal;

            if ($isFractionable && $unitsPerBox > 0) {
                $cajas = floor(abs($stock));
                $fraccion = abs($stock) - $cajas;
                $unidades = round($fraccion * $unitsPerBox);

                $texto = [];

                if ($cajas > 0) {
                    $texto[] = "{$cajas} caj";
                }

                if ($unidades > 0) {
                    $texto[] = "{$unidades} und";
                }

                return empty($texto) ? '0 und' : implode(' y ', $texto);
            }

            if ($isWeighable) {
                $sufijo = match ($unitCode) {
                    'KGM' => 'kg',
                    'LTR' => 'lt',
                    'GLL' => 'gal',
                    default => 'und',
                };

                return number_format($stock, 2) . " {$sufijo}";
            }

            return number_format($stock, 0) . ' und';
        };

        $isFractionable = $get('_is_fractionable') ?? false;
        $isWeighable = $get('_is_weighable') ?? false;
        $unitCode = $get('unit_code') ?? 'NIU';

        $producto = Product::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->find($get('product_id'));

        $unitsPerBox = $producto ? $producto->units_per_box : 0;

        $totalStockDecimal = $get('_stock_disponible') ?? 0;
        $textoTotal = $traducirStock(
            $totalStockDecimal,
            $isFractionable,
            $isWeighable,
            $unitCode,
            $unitsPerBox
        );

        if ($batchId = $get('product_batch_id')) {
            $batch = \Percy\Core\Models\ProductBatch::query()
                ->where('tenant_id', Auth::user()->tenant_id)
                ->where('product_id', $get('product_id'))
                ->find($batchId);

            $loteStockDecimal = $batch ? $batch->current_quantity : 0;

            $textoLote = $traducirStock(
                $loteStockDecimal,
                $isFractionable,
                $isWeighable,
                $unitCode,
                $unitsPerBox
            );

            return "Stock del lote: {$textoLote}. Stock total: {$textoTotal}.";
        }

        return "Stock total disponible: {$textoTotal}.";
    }

    private static function extractWebNote(?Sale $record, string $label): ?string
    {
        if (! $record?->kitchen_notes) {
            return null;
        }

        $pattern = '/^' . preg_quote($label, '/') . ':\s*(.+)$/mi';

        if (preg_match($pattern, $record->kitchen_notes, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
