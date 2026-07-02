<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Percy\Core\Models\Sale;
use Percy\Core\Models\Product;
use Percy\Core\Models\Category;
use Percy\Core\Services\Sales\CorrelativeService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use App\Filament\Pages\PosRestaurant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;

class PosOrder extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string $view = 'filament.pages.pos-order';
    protected static ?string $title = 'Punto de Venta';
    protected static ?string $slug = 'pos-order/{sale}';
    protected static bool $shouldRegisterNavigation = false;

    // 🌟 MAGIA UX: Obligamos a la página a usar el 100% del monitor
    protected ?string $maxContentWidth = 'full';

    public Sale $sale;

    // Categoría seleccionada actualmente (null = Todas)
    public $selectedCategoryId = null;

    // 🌟 Agrega esto a tus propiedades
    public $activeCashRegister;

    // 🌟 MAGIA UX: Cambia el título grande de la página dinámicamente
    public function getHeading(): string
    {
        $nombreMesa = $this->sale->table->name ?? 'Mesa';

        // Obtenemos el nombre del mozo dueño de la cuenta (solo su primer nombre para no saturar)
        $nombreMozo = $this->sale->user ? explode(' ', $this->sale->user->name)[0] : 'Cajero';

        return "Atendiendo: {$nombreMesa} (Mozo: {$nombreMozo})";
    }

    public function mount(Sale $sale)
    {
        abort_unless($sale->tenant_id === Auth::user()->tenant_id, 403);
        $this->sale = $sale;

        // 🌟 Buscamos la caja abierta de este local
        $this->activeCashRegister = \Percy\Core\Models\CashRegister::where('tenant_id', Auth::user()->tenant_id)
            ->where('status', 'open') // Asumo que usas 'open' o 'abierta', ajusta si es necesario
            ->first();
    }

    // 🌟 TRAEMOS LAS CATEGORÍAS
    #[Computed]
    public function categories()
    {
        return Category::where('tenant_id', Auth::user()->tenant_id)
            ->where('active', true)
            ->get();
    }

    // 🌟 TRAEMOS LOS PRODUCTOS (Filtrados por la categoría tocada)
    #[Computed]
    public function products()
    {
        $query = Product::where('tenant_id', Auth::user()->tenant_id)
            ->where('active', true);

        if ($this->selectedCategoryId) {
            $query->where('category_id', $this->selectedCategoryId);
        }

        return $query->get();
    }

    // 🌟 ACCIÓN: Cambiar de categoría al tocar un botón
    public function setCategory($id)
    {
        $this->selectedCategoryId = $id;
    }

    // 🌟 ACCIÓN PRINCIPAL: Agregar plato a la comanda
    public function addProduct($productId)
    {
        // 🌟 EL CANDADO DE CAJA: Si no hay caja abierta, bloqueamos todo
        if (!$this->activeCashRegister) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Caja Cerrada')
                ->body('Debe haber una caja abierta en el local para registrar pedidos.')
                ->send();
            return;
        }

        $product = \Percy\Core\Models\Product::where('tenant_id', Auth::user()->tenant_id)->find($productId);
        if (!$product) return;

        // Buscamos si el plato ya está en la cuenta para sumarle 1
        $existingItem = $this->sale->items()->where('product_id', $product->id)->first();

        // Cantidad que el cliente quiere tener en total
        $cantidadDeseada = $existingItem ? $existingItem->quantity + 1 : 1;

        // 🌟 CANDADO DE STOCK (Solo aplica a Productos Físicos, ignora los Servicios)
        if ($product->type === 'product') {

            // NOTA: Cambia 'stock' por el nombre real de tu columna en la BD si se llama diferente (ej. 'current_stock', 'quantity')
            $stockDisponible = $product->current_stock ?? 0;

            if ($stockDisponible < $cantidadDeseada) {
                \Filament\Notifications\Notification::make()
                    ->warning()
                    ->title('¡Stock Agotado!')
                    ->body("No puedes agregar más {$product->name}. Solo quedan {$stockDisponible} en inventario.")
                    ->send();

                return; // Detiene la ejecución. No se agrega nada a la venta.
            }
        }

        // 🌟 APAGAMOS EL KARDEX LOCO (El Observer no hará nada)
        \Percy\Core\Observers\SaleItemObserver::$muteStockDeduction = true;

        // Si pasó el candado de stock (o es un servicio), procedemos a agregarlo
        if ($existingItem) {

            // 🌟 CORRECCIÓN ERROR 3103 SUNAT: Recalculamos Totales e IGV por la nueva cantidad
            $nuevoTotal = $cantidadDeseada * $existingItem->unit_price;
            $nuevoIgvLinea = $cantidadDeseada * ($existingItem->unit_price - $existingItem->unit_value);

            $existingItem->update([
                'quantity' => $cantidadDeseada,
                'total' => $nuevoTotal,
                'igv_amount' => round($nuevoIgvLinea, 2), // <- Ahora SUNAT recibirá el IGV correcto de la fila
            ]);
        } else {
            // Si es nuevo, lo agregamos a la comanda
            $afectacion = \Percy\Core\Models\AfectacionIgv::find($product->afectacion_igv_id ?? 1);
            $porcentaje = ($afectacion && $afectacion->gravado) ? ($afectacion->porcentaje / 100) : 0;
            $unitValue = $product->price / (1 + $porcentaje);
            $igvAmount = $product->price - $unitValue;

            $this->sale->items()->create([
                'product_id' => $product->id,
                'item_name' => $product->name,
                'quantity' => 1,
                'unit_price' => $product->price,
                'total' => $product->price,
                'measurement_unit' => 'unit',
                'afectacion_igv_id' => $product->afectacion_igv_id,
                'unit_code' => $product->unidadSunat ? $product->unidadSunat->codigo : 'NIU',
                'unit_value' => round($unitValue, 2),
                'igv_amount' => round($igvAmount, 2),
            ]);
        }

        $this->recalculateTotals();
    }

    // 🌟 ACCIÓN: Eliminar plato de la comanda
    public function removeItem($itemId)
    {
        $item = $this->sale->items()->find($itemId);
        if (!$item) return;

        if ($item->sent_quantity > 0 && Auth::user()->hasRole('Vendedor')) {
            \Filament\Notifications\Notification::make()->danger()->title('Plato en Cocina')->body('Este plato ya se está preparando. Pide a un Administrador que lo anule.')->send();
            return;
        }

        // 🌟 MAGIA FINANCIERA: Si el admin borra algo ya cocinado, devolvemos el stock
        if ($item->sent_quantity > 0) {
            $observer = new \Percy\Core\Observers\SaleItemObserver();
            $observer->refundStock($item, $item->sent_quantity);
        }

        $item->delete();
        $this->recalculateTotals();
    }

    // 🌟 RECALCULAR TOTALES MÁGICAMENTE
    private function recalculateTotals()
    {
        // Refrescamos la relación para tener los datos nuevecitos
        $this->sale->load('items');

        $total = $this->sale->items->sum('total');

        // Forma simplificada de totales para restaurante rápido (todo gravado)
        // Luego lo puliremos con op_gravadas si lo requieres
        $this->sale->update([
            'total' => $total,
            'op_gravadas' => $total / 1.18,
            'igv' => $total - ($total / 1.18),
        ]);

        // 🌟 MAGIA UX/UI: Controlamos el color de la mesa según los platos
        $itemsCount = $this->sale->items->count();

        if ($itemsCount > 0) {
            // Si hay al menos un producto, y la mesa está libre, la ocupamos (ROJO)
            if ($this->sale->table?->status !== \Percy\Core\Models\Table::STATUS_OCCUPIED) {
                $this->sale->table?->update(['status' => \Percy\Core\Models\Table::STATUS_OCCUPIED]);
            }
        } else {
            // Si el mozo quitó todos los productos y quedó en cero, la liberamos (VERDE)
            if ($this->sale->table?->status !== \Percy\Core\Models\Table::STATUS_AVAILABLE) {
                $this->sale->table?->update(['status' => \Percy\Core\Models\Table::STATUS_AVAILABLE]);
            }
        }
    }

    // 🌟 ACCIÓN: Aumentar cantidad
    public function incrementItem($itemId)
    {
        $item = $this->sale->items()->find($itemId);
        if ($item) {
            $item->update([
                'quantity' => $item->quantity + 1,
                'total' => ($item->quantity + 1) * $item->unit_price,
            ]);
            $this->recalculateTotals();
        }
    }

    // 🌟 ACCIÓN: Disminuir cantidad
    public function decrementItem($itemId)
    {
        $item = $this->sale->items()->find($itemId);
        if (!$item) return;

        if ($item->quantity <= $item->sent_quantity && Auth::user()->hasRole('Vendedor')) {
            \Filament\Notifications\Notification::make()->danger()->title('Bloqueado')->body('No puedes reducir platos que ya están en cocina. Llama al Administrador.')->send();
            return;
        }

        if ($item->quantity > 1) {
            $newQuantity = $item->quantity - 1;

            // 🌟 MAGIA FINANCIERA: Si el admin reduce por debajo de lo enviado a cocina
            if ($newQuantity < $item->sent_quantity) {
                $refundQty = $item->sent_quantity - $newQuantity;
                $item->sent_quantity = $newQuantity; // Ajustamos la memoria de cocina

                $observer = new \Percy\Core\Observers\SaleItemObserver();
                $observer->refundStock($item, $refundQty);
            }

            $item->update([
                'quantity' => $newQuantity,
                'sent_quantity' => $item->sent_quantity, // Guardamos el ajuste
                'total' => $newQuantity * $item->unit_price,
            ]);
        } else {
            $this->removeItem($itemId); // Reutilizamos la lógica completa de borrar
        }
        $this->recalculateTotals();
    }

    // 🌟 EL MOTOR DE DESCUENTOS INTELIGENTE
    private function processPendingInventory()
    {
        $observer = new \Percy\Core\Observers\SaleItemObserver();

        // 🌟 LA CURA AL LAZY LOADING: Cargamos los productos y la venta por adelantado
        // para que el Observer no tenga que hacer consultas extra por cada plato.
        $this->sale->loadMissing(['items.product', 'items.sale']);

        foreach ($this->sale->items as $item) {
            // Calculamos qué falta enviar (Ej: Pidió 4, ya se enviaron 3 = Falta 1)
            $pendingQuantity = $item->quantity - $item->sent_quantity;

            if ($pendingQuantity > 0) {
                try {
                    // Descontamos solo la diferencia
                    $observer->deductStock($item, $pendingQuantity);

                    // Actualizamos que ya se envió
                    $item->update(['sent_quantity' => $item->quantity]);
                } catch (\Exception $e) {
                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('Error de Stock')
                        ->body($e->getMessage())
                        ->send();
                }
            }
        }
    }

    // 🌟 NUEVO BOTÓN MODERNO: Enviar a Cocina
    public function sendToKitchenAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('sendToKitchen')
            ->label('A Cocina')
            ->color('warning')
            ->icon('heroicon-m-fire')
            ->extraAttributes(['class' => 'w-full h-full text-lg shadow-md [&>button]:w-full [&>button]:justify-center [&>button]:h-full'])
            ->requiresConfirmation() // 🌟 EL MODAL MODERNO
            ->modalHeading('Enviar a Cocina')
            ->modalDescription('¿Estás seguro de enviar este pedido a cocina? Revisa bien las cantidades, una vez enviado solo un administrador podrá anularlo y afectar el inventario.')
            ->modalSubmitActionLabel('Sí, enviar a cocina')
            ->action(function () {
                if ($this->sale->items()->count() === 0) {
                    \Filament\Notifications\Notification::make()->warning()->title('Comanda vacía')->body('Agrega al menos un plato.')->send();
                    return;
                }

                $platosNuevos = [];
                foreach ($this->sale->items as $item) {
                    $pendientes = $item->quantity - $item->sent_quantity;
                    if ($pendientes > 0) {
                        $platosNuevos[$item->id] = $pendientes;
                    }
                }

                if (empty($platosNuevos)) {
                    \Filament\Notifications\Notification::make()->info()->title('Sin cambios')->body('No hay platos nuevos para enviar.')->send();
                    return;
                }

                $this->processPendingInventory();

                \Filament\Notifications\Notification::make()->success()->title('¡Enviado a Cocina!')->send();

                $query = http_build_query(['send' => $platosNuevos]);
                $kitchenTicketUrl = url('/print/kitchen/' . $this->sale->id . '?' . $query);

                $this->js("window.open('{$kitchenTicketUrl}', '_blank');");
                return redirect()->to(PosRestaurant::getUrl());
            });
    }

    // 🌟 AGREGAR NOTA A UN PLATO ESPECÍFICO
    public function updateItemNote($itemId, $note)
    {
        $item = $this->sale->items()->find($itemId);
        if ($item) {
            $item->update(['note' => $note]);
            $this->sale->refresh(); // Refrescamos la venta para que se vea en pantalla
        }
    }

    // 🌟 ACCIÓN: Modal de Cobrar y Liberar Mesa
    public function checkoutAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('checkout')
            ->label('Cobrar')
            ->color('success')
            ->icon('heroicon-m-banknotes')
            ->visible(fn() => !Auth::user()->hasRole('Vendedor'))
            ->extraAttributes(['class' => 'w-full [&>button]:w-full [&>button]:justify-center'])
            ->modalHeading('Finalizar Venta')
            ->modalDescription('Selecciona el comprobante y método de pago.')
            ->modalSubmitActionLabel('Confirmar Pago y Liberar Mesa')
            ->form([
                \Filament\Forms\Components\Select::make('document_type')
                    ->label('Tipo de Comprobante')
                    ->options([
                        '00' => 'Nota de Venta (Interno)',
                        '03' => 'Boleta Electrónica',
                        '01' => 'Factura Electrónica',
                    ])
                    ->default('00')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (\Filament\Forms\Set $set, $state) {
                        $set('customer_id', null);

                        // Buscamos la serie (usamos value() que es más rápido que first()->serie)
                        $serieAuto = \Percy\Core\Models\Serie::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                            ->where('document_type', $state)
                            ->where('active', true)
                            ->value('serie');

                        // Inyectamos el valor
                        $set('serie_documento', $serieAuto);
                    }),

                \Filament\Forms\Components\Select::make('serie_documento')
                    ->label('Serie del Comprobante')
                    // 🌟 LA MAGIA ABSOLUTA: Esto fuerza a reconstruir el HTML del selector
                    ->key(fn(\Filament\Forms\Get $get) => 'serie_dinamica_' . $get('document_type'))
                    ->options(function (\Filament\Forms\Get $get) {
                        $docType = $get('document_type');
                        return \Percy\Core\Models\Serie::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                            ->where('document_type', $docType)
                            ->where('active', true)
                            ->pluck('serie', 'serie');
                    })
                    ->default(function () {
                        return \Percy\Core\Models\Serie::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                            ->where('document_type', '00')
                            ->where('active', true)
                            ->value('serie');
                    })
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live()
                    ->validationMessages([
                        'required' => '⚠️ No hay series para este comprobante.',
                    ]),

                \Filament\Forms\Components\Select::make('customer_id')
                    ->label('Cliente')
                    ->options(function (\Filament\Forms\Get $get) {
                        $docType = $get('document_type');
                        $query = \Percy\Core\Models\Customer::where('tenant_id', Auth::user()->tenant_id);

                        if ($docType === '01') {
                            $query->whereIn('document_type', ['RUC', '6']);
                        } elseif (in_array($docType, ['03', '00'])) {
                            $query->whereIn('document_type', ['DNI', '1', 'CE', '0', '7', '4']);
                        }

                        return $query->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->required(fn(\Filament\Forms\Get $get) => $get('document_type') === '01')
                    ->helperText(fn(\Filament\Forms\Get $get) => $get('document_type') === '01' ? 'Obligatorio para Facturas' : 'Opcional. Déjalo en blanco para Consumidor Final.')
                    ->manageOptionActions(function (\Filament\Forms\Components\Actions\Action $action) {
                        return $action
                            ->icon('heroicon-o-user-plus')
                            ->color('success')
                            ->tooltip('Agregar Cliente Nuevo');
                    })
                    ->createOptionModalHeading('Registrar Nuevo Cliente')
                    ->createOptionForm([
                        \Filament\Forms\Components\Section::make('Identidad del Cliente')->schema([
                            \Filament\Forms\Components\Select::make('document_type')
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

                            \Filament\Forms\Components\TextInput::make('document_number')
                                ->label('Número')
                                ->maxLength(fn(\Filament\Forms\Get $get) => match ($get('document_type')) {
                                    'DNI' => 8,
                                    'RUC' => 11,
                                    default => 15
                                })
                                ->minLength(fn(\Filament\Forms\Get $get) => match ($get('document_type')) {
                                    'DNI' => 8,
                                    'RUC' => 11,
                                    default => null
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
                                            try {
                                                $token = config('services.decolecta.token');
                                                $response = \Illuminate\Support\Facades\Http::withToken($token)->timeout(10)->get("https://api.decolecta.com/v1/sunat/ruc?numero={$state}");

                                                if ($response->successful()) {
                                                    $data = $response->json();
                                                    $set('name', $data['razon_social'] ?? '');
                                                    $dir = trim($data['direccion'] ?? '');
                                                    $dep = trim($data['departamento'] ?? '');
                                                    $prov = trim($data['provincia'] ?? '');
                                                    $dist = trim($data['distrito'] ?? '');
                                                    $set('address', preg_replace('/\s+/', ' ', trim("$dir $dep - $prov - $dist", " -")));
                                                    \Filament\Notifications\Notification::make()->success()->title('RUC Encontrado')->send();
                                                } else {
                                                    \Filament\Notifications\Notification::make()->danger()->title('No encontrado')->body('RUC no existe en SUNAT.')->send();
                                                }
                                            } catch (\Exception $e) {
                                                \Filament\Notifications\Notification::make()->danger()->title('Error de conexión')->body('No se pudo conectar con la API.')->send();
                                            }
                                        })
                                ),

                            \Filament\Forms\Components\TextInput::make('name')
                                ->label('Nombre Completo o Razón Social')
                                ->required()
                                ->maxLength(150)
                                ->columnSpanFull(),
                        ])->columns(['default' => 1, 'sm' => 2]),

                        \Filament\Forms\Components\Section::make('Datos de Contacto')->schema([
                            \Filament\Forms\Components\TextInput::make('phone')->label('Teléfono')->tel()->maxLength(30)->columnSpan(1),
                            \Filament\Forms\Components\TextInput::make('email')->label('Correo Electrónico')->email()->maxLength(150)->columnSpan(1),
                            \Filament\Forms\Components\Textarea::make('address')->label('Dirección Fija')->maxLength(255)->rows(2)->columnSpanFull(),
                        ])->columns(['default' => 1, 'sm' => 2])->collapsible(),
                    ])
                    ->createOptionUsing(function (array $data) {
                        $data['tenant_id'] = Auth::user()->tenant_id;
                        $customer = \Percy\Core\Models\Customer::create($data);
                        return $customer->id;
                    }),

                \Filament\Forms\Components\Select::make('payment_method')
                    ->label('Método de Pago')
                    ->options(\Percy\Core\Models\Sale::PAYMENT_METHODS)
                    ->default('Efectivo')
                    ->live()
                    ->required()
                    ->afterStateUpdated(fn(\Filament\Forms\Set $set) => $set('payment_reference', null)),

                \Filament\Forms\Components\TextInput::make('payment_reference')
                    ->label('N° de Operación / Referencia')
                    ->placeholder('Ej: 123456')
                    ->visible(fn(\Filament\Forms\Get $get) => \Percy\Core\Models\Sale::requiresReference($get('payment_method') ?? ''))
                    ->required(fn(\Filament\Forms\Get $get) => \Percy\Core\Models\Sale::requiresReference($get('payment_method') ?? '')),
            ])
            ->action(function (array $data) {
                // 1. Validar que la comanda no esté vacía
                if ($this->sale->items()->count() === 0) {
                    \Filament\Notifications\Notification::make()->warning()->title('Comanda vacía')->body('Agrega al menos un plato antes de cobrar.')->send();
                    return;
                }

                $newDocType = $data['document_type'];
                $currentDocType = $this->sale->document_type; // Normalmente es '00'

                $finalSerie = $this->sale->series;
                $finalCorrelative = $this->sale->correlative;

                // 2. 🌟 MAGIA ACTUALIZADA: Usamos la serie que el cliente seleccionó en el formulario
                if ($newDocType !== $currentDocType) {
                    try {
                        $finalSerie = $data['serie_documento'];

                        $finalCorrelative = app(CorrelativeService::class)
                            ->next(Auth::user()->tenant_id, $newDocType, $finalSerie);
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Falta Serie')
                            ->body($e->getMessage())
                            ->send();

                        return;
                    }
                }

                // 3. Calculamos el monto en letras
                $formatter = new \Luecano\NumeroALetras\NumeroALetras();
                $legendText = $formatter->toInvoice($this->sale->total, 2, 'SOLES');

                // 🌟 POR SI PIDIÓ ALGO A ÚLTIMA HORA Y NO LO ENVIÓ A COCINA
                $this->processPendingInventory();

                // 4. Cerramos la cuenta
                $this->sale->update([
                    'document_type' => $newDocType,
                    'series' => $finalSerie,
                    'correlative' => $finalCorrelative,
                    'customer_id' => $data['customer_id'] ?? null,
                    'payment_method' => $data['payment_method'],
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'status' => 'completed',
                    'legend_text' => $legendText,
                    'cash_register_id' => $this->activeCashRegister->id,
                ]);

                // 5. Liberamos la Mesa
                $this->sale->table?->update(['status' => \Percy\Core\Models\Table::STATUS_AVAILABLE]);

                \Filament\Notifications\Notification::make()->success()->title('Pago Exitoso')->body('La mesa ha sido liberada correctamente.')->send();

                // 6. Construimos las URLs
                $ticketUrl = url('/print/ticket/' . $this->sale->id);
                $mapUrl = \App\Filament\Pages\PosRestaurant::getUrl();

                // Ejecutamos JavaScript
                $this->js("
                    window.open('{$ticketUrl}', '_blank');
                    window.location.href = '{$mapUrl}';
                ");
            });
    }
}
