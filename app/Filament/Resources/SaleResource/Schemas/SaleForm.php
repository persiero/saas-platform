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
use Percy\Core\Models\AfectacionIgv;

class SaleForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                // 🌟 1. AVISO PARA EL CAJERO (Solo visible en ventas web pendientes)
                Forms\Components\Section::make('🛍️ PEDIDO WEB PENDIENTE DE COBRO')
                    ->description('Verifique el pago con el cliente y asigne el tipo de comprobante. Al "Guardar cambios", se emitirá el documento final y no podrá editarse más.')
                    ->schema([
                        Forms\Components\Placeholder::make('kitchen_notes')
                            ->label('Datos ingresados por el cliente en la tienda web:')
                            ->content(fn(?Sale $record) => $record ? $record->kitchen_notes : 'Sin datos')
                            ->extraAttributes(['class' => 'font-mono text-sm text-gray-700 bg-yellow-50 p-4 rounded-lg'])
                    ])
                    // 🌟 SOLUCIÓN: Quitamos ->color() y usamos extraAttributes para darle un borde amarillo llamativo
                    ->extraAttributes(['class' => 'ring-2 ring-amber-500'])
                    ->visible(fn(?Sale $record) => $record && $record->channel === 'ecommerce' && $record->status === 'pending_payment'),

                Forms\Components\Group::make()->schema([
                    // 🌟 1. INFORMACIÓN DE VENTA (Responsivo)
                    Forms\Components\Section::make('Información de Venta')->schema([
                        Forms\Components\Select::make('document_type')
                            ->label('Tipo de Comprobante')
                            ->options([
                                '03' => 'Boleta Electrónica',
                                '01' => 'Factura Electrónica',
                                '00' => 'Nota de Venta (Interno)',
                            ])
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
                            ->helperText(fn(\Filament\Forms\Get $get) => $get('document_type') === '01' ? 'Obligatorio para Facturas' : 'Opcional. Déjalo en blanco para Consumidor Final.')
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
                            ->label('N° de Operación / Referencia')
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
                            ->helperText('SUNAT solo acepta documentos de los últimos 7 días')
                            ->required()
                            ->disabled(fn(?Sale $record) => $record && $record->status !== 'pending_payment')
                            ->columnSpan(['default' => 1, 'md' => 1]),
                        // 🌟 SOLUCIÓN: Agregamos estos campos como Hidden para que Filament permita guardarlos
                        //Forms\Components\Hidden::make('status'),
                        //Forms\Components\Hidden::make('user_id'),
                        //Forms\Components\Hidden::make('sunat_status'),
                        // (El de 'correlative' ya lo tenías oculto arriba, así que ese está bien)

                        Forms\Components\TextInput::make('prescription_code')
                            ->label('N° de Receta Médica / CMP')
                            ->placeholder('Ej: CMP 12345')
                            ->maxLength(255)
                            ->visible(function (Forms\Get $get) {
                                $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                if (!($features['has_lots'] ?? false)) return false;
                                $items = $get('items') ?? [];
                                foreach ($items as $item) {
                                    if (!empty($item['product_id'])) {
                                        $product = \Percy\Core\Models\Product::find($item['product_id']);
                                        if ($product && $product->requires_prescription) return true;
                                    }
                                }
                                return false;
                            })
                            ->required(function (Forms\Get $get) {
                                $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                if (!($features['has_lots'] ?? false)) return false;
                                $items = $get('items') ?? [];
                                foreach ($items as $item) {
                                    if (!empty($item['product_id'])) {
                                        $product = \Percy\Core\Models\Product::find($item['product_id']);
                                        if ($product && $product->requires_prescription) return true;
                                    }
                                }
                                return false;
                            })
                            ->columnSpan(['default' => 1, 'md' => 1]),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(['completed' => 'Completado', 'pending_payment' => 'Pendiente de Cobro', 'canceled' => 'Anulado'])
                            ->default('completed')
                            ->hidden(),
                    ])->columns(['default' => 1, 'md' => 4]), // 🌟 El contenedor usa 1 col en móvil, 4 en PC

                    // 🌟 2. DETALLE DE PRODUCTOS
                    Forms\Components\Section::make('Detalle de Productos')->schema([

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
                                    $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                    $batch = null;

                                    if ($features['has_lots'] ?? false) {
                                        $batch = \Percy\Core\Models\ProductBatch::where('product_id', $product->id)
                                            ->where('current_quantity', '>', 0)
                                            ->whereDate('expiration_date', '>=', now())
                                            ->orderBy('expiration_date', 'asc')
                                            ->first();
                                    }

                                    // 🌟 1. Obtenemos la configuración completa del Tenant
                                    $tenant = \Illuminate\Support\Facades\Auth::user()->tenant;
                                    $tenantIgv = $tenant->igv_percentage ?? 18;
                                    $includesIgv = $tenant->prices_include_igv ?? true; // <-- Leemos el interruptor

                                    $afectacion = \Percy\Core\Models\AfectacionIgv::find($product->afectacion_igv_id ?? 1);

                                    //Si el producto está gravado, usamos el IGV del Tenant, no el de la tabla general
                                    $porcentaje = ($afectacion && $afectacion->gravado) ? ($tenantIgv / 100) : 0;

                                    // 🌟 2. LÓGICA DEL INTERRUPTOR
                                    $precioCatalogo = $product->price;
                                    $precioFraccionCatalogo = $product->unit_price ?? 0;

                                    if ($includesIgv) {
                                        // El catálogo ya es el precio final
                                        $precioVentaFinal = $precioCatalogo;
                                        $precioFraccionFinal = $precioFraccionCatalogo;
                                        $unitValue = $precioCatalogo / (1 + $porcentaje);
                                    } else {
                                        // El catálogo es la base, debemos SUMARLE el IGV para el cliente
                                        $unitValue = $precioCatalogo;
                                        $precioVentaFinal = $precioCatalogo * (1 + $porcentaje);
                                        $precioFraccionFinal = $precioFraccionCatalogo * (1 + $porcentaje);
                                    }

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
                                    ->columnSpan(['default' => 1, 'md' => 3])
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
                                            $product = \Percy\Core\Models\Product::find($state);

                                            // 1. Leemos el interruptor y la tasa
                                            $tenant = \Illuminate\Support\Facades\Auth::user()->tenant;
                                            $tenantIgv = $tenant->igv_percentage ?? 18;
                                            $includesIgv = $tenant->prices_include_igv ?? true;

                                            $afectacion = \Percy\Core\Models\AfectacionIgv::find($product->afectacion_igv_id ?? 1);
                                            $porcentaje = ($afectacion && $afectacion->gravado) ? ($tenantIgv / 100) : 0;

                                            // 2. Matemáticas según el interruptor
                                            $precioVentaFinal = $includesIgv ? $product->price : ($product->price * (1 + $porcentaje));
                                            $precioFraccionFinal = $includesIgv ? ($product->unit_price ?? 0) : (($product->unit_price ?? 0) * (1 + $porcentaje));

                                            // 3. Asignamos los precios calculados
                                            $set('unit_price', round($precioVentaFinal, 2));
                                            $set('_box_price', round($precioVentaFinal, 2));
                                            $set('_fraction_price', round($precioFraccionFinal, 2));

                                            // Estos quedan igual
                                            $set('afectacion_igv_id', $product->afectacion_igv_id);
                                            $set('unit_code', $product->unidadSunat ? $product->unidadSunat->codigo : 'NIU');
                                            $set('item_name', $product->name);
                                            $set('_stock_disponible', $product->current_stock);
                                            $set('_is_fractionable', $product->is_fractionable);
                                            $set('measurement_unit', 'box');
                                            $set('_is_weighable', $product->is_weighable);

                                            $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                            $loteProximo = null;
                                            if ($features['has_lots'] ?? false) {
                                                $loteProximo = \Percy\Core\Models\ProductBatch::where('product_id', $state)
                                                    ->where('current_quantity', '>', 0)
                                                    ->whereDate('expiration_date', '>=', now())
                                                    ->orderBy('expiration_date', 'asc')
                                                    ->where('is_active', true)
                                                    ->first();
                                            }
                                            $set('product_batch_id', $loteProximo ? $loteProximo->id : null);
                                        }
                                        SaleFormCalculations::updateRow($get, $set);
                                        SaleFormCalculations::updateTotals($get, $set);
                                    }),

                                // 🌟 NUEVO CAMPO: Solo se muestra para el Servicio de Delivery
                                Forms\Components\TextInput::make('item_name')
                                    ->label('Servicio Adicional')
                                    ->disabled() // El cajero no puede editar el nombre "Servicio de Delivery"
                                    ->columnSpan(['default' => 1, 'md' => 3])
                                    // Solo se hace visible si el campo de arriba (product_id) está vacío
                                    ->visible(fn(\Filament\Forms\Get $get) => empty($get('product_id')) && !empty($get('item_name'))),

                                Forms\Components\Select::make('measurement_unit')
                                    ->label('Presentación')
                                    ->options(['box' => 'Caja', 'unit' => 'Unidad'])
                                    ->visible(fn(Get $get) => $get('_is_fractionable'))
                                    ->required(fn(Get $get) => $get('_is_fractionable'))
                                    ->columnSpan(['default' => 1, 'md' => 2])
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
                                        $productId = $get('product_id');
                                        if (!$productId) return [];
                                        return \Percy\Core\Models\ProductBatch::where('product_id', $productId)
                                            ->where('current_quantity', '>', 0)
                                            ->whereDate('expiration_date', '>=', now())
                                            ->orderBy('expiration_date', 'asc')
                                            ->where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(function ($batch) {
                                                $vence = $batch->expiration_date ? $batch->expiration_date->format('d/m/Y') : 'N/A';
                                                return [$batch->id => "{$batch->batch_number} (Vence: {$vence})"];
                                            });
                                    })
                                    ->visible(fn() => \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features['has_lots'] ?? false)
                                    ->required(fn() => \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features['has_lots'] ?? false)
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(['default' => 1, 'md' => 2]),

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
                                            $batch = \Percy\Core\Models\ProductBatch::find($batchId);
                                            $stock = $batch ? $batch->current_quantity : null;
                                        } elseif ($productId = $get('product_id')) {
                                            $product = \Percy\Core\Models\Product::find($productId);
                                            $stock = $product ? $product->current_stock : null;
                                        }
                                        if ($stock === null) return null;
                                        if ($get('measurement_unit') === 'unit') return 99999;
                                        return $stock;
                                    })
                                    ->required()
                                    ->columnSpan(['default' => 1, 'md' => 2])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(\Filament\Forms\Get $get, \Filament\Forms\Set $set) => [SaleFormCalculations::updateRow($get, $set), SaleFormCalculations::updateTotals($get, $set)])
                                    ->helperText(function (\Filament\Forms\Get $get) {
                                        if (!$get('product_id')) return null;

                                        // Función auxiliar interna para traducir números a texto humano (ABREVIADO)
                                        $traducirStock = function ($stockDecimal, $isFractionable, $isWeighable, $unitCode, $unitsPerBox) {
                                            $stock = (float) $stockDecimal;

                                            // 1. Fraccionable (Farmacia)
                                            if ($isFractionable && $unitsPerBox > 0) {
                                                $cajas = floor(abs($stock));
                                                $fraccion = abs($stock) - $cajas;
                                                $unidades = round($fraccion * $unitsPerBox);

                                                $texto = [];
                                                // 🌟 REDUCIMOS TEXTO: Usamos "caj" y "und" en minúsculas
                                                if ($cajas > 0) $texto[] = "{$cajas} caj";
                                                if ($unidades > 0) $texto[] = "{$unidades} und";

                                                return empty($texto) ? '0 und' : implode(' y ', $texto);
                                            }

                                            // 2. Granel (Peso/Volumen)
                                            if ($isWeighable) {
                                                $sufijo = match ($unitCode) {
                                                    'KGM' => 'kg',
                                                    'LTR' => 'lt',
                                                    'GLL' => 'gal',
                                                    default => 'und'
                                                };
                                                return number_format($stock, 2) . " {$sufijo}";
                                            }

                                            // 3. Normal
                                            return number_format($stock, 0) . ' und';
                                        };

                                        $isFractionable = $get('_is_fractionable') ?? false;
                                        $isWeighable = $get('_is_weighable') ?? false;
                                        $unitCode = $get('unit_code') ?? 'NIU';

                                        $producto = \Percy\Core\Models\Product::find($get('product_id'));
                                        $unitsPerBox = $producto ? $producto->units_per_box : 0;

                                        $totalStockDecimal = $get('_stock_disponible') ?? 0;
                                        $textoTotal = $traducirStock($totalStockDecimal, $isFractionable, $isWeighable, $unitCode, $unitsPerBox);

                                        if ($batchId = $get('product_batch_id')) {
                                            $batch = \Percy\Core\Models\ProductBatch::find($batchId);
                                            $loteStockDecimal = $batch ? $batch->current_quantity : 0;
                                            $textoLote = $traducirStock($loteStockDecimal, $isFractionable, $isWeighable, $unitCode, $unitsPerBox);

                                            return "Lote: {$textoLote} | Total: {$textoTotal}";
                                        }

                                        return "Stock Total: {$textoTotal}";
                                    }),

                                Forms\Components\Grid::make(['default' => 2])
                                    ->schema([
                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Precio Unit.')
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
                                    ->columnSpan(['default' => 1, 'md' => 3]), // 🌟 El Grid de precio ocupará las últimas 2 columnas

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
                            ->columns(['default' => 1, 'md' => 12]) // 🌟 REPEATER DE 12 COLUMNAS EN PC, 1 EN MÓVIL
                            ->defaultItems(0)
                            ->addActionLabel('Agregar otro producto'),
                    ]),
                ])->columnSpan(['default' => 1, 'lg' => 3]), // 🌟 GRUPO IZQUIERDO

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Resumen Financiero')->schema([
                        Forms\Components\Placeholder::make('op_gravadas_lbl')
                            ->label('Op. Gravadas')
                            ->content(fn(Get $get): string => 'S/ ' . number_format((float)($get('op_gravadas') ?? 0), 2))
                            ->extraAttributes(['class' => 'flex justify-between border-b pb-1']),

                        Forms\Components\Placeholder::make('op_exoneradas_lbl')
                            ->label('Op. Exoneradas')
                            ->content(fn(Get $get): string => 'S/ ' . number_format((float)($get('op_exoneradas') ?? 0), 2))
                            ->extraAttributes(['class' => 'flex justify-between border-b pb-1 text-gray-500']),

                        Forms\Components\Placeholder::make('op_inafectas_lbl')
                            ->label('Op. Inafectas')
                            ->content(fn(Get $get): string => 'S/ ' . number_format((float)($get('op_inafectas') ?? 0), 2))
                            ->extraAttributes(['class' => 'flex justify-between border-b pb-1 text-gray-500']),

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
                ])->columnSpan(['default' => 1, 'lg' => 4]), // 🌟 GRUPO DERECHO
            ])
            ->columns(['default' => 1, 'lg' => 4]);
    }

}
