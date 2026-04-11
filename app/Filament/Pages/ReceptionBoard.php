<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Percy\Core\Models\Room;
use Percy\Core\Models\Reception;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\Action;
use Filament\Forms;

class ReceptionBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Módulo de Recepción';
    protected static string $view = 'filament.pages.reception-board';
    protected static ?string $title = 'Monitor de Habitaciones';
    protected static ?int $navigationSort = 1;

    // 🌟 OPCIONAL: Forzamos ancho completo igual que en el restaurante
    protected ?string $maxContentWidth = 'full';

    public static function canAccess(): bool
    {
        $features = Auth::user()->tenant->businessSector->features ?? [];
        return $features['has_rooms'] ?? false;
    }

    protected function getViewData(): array
    {
        $tenantId = Auth::user()->tenant_id;

        // 🌟 MAGIA: Traemos las Zonas activas que pertenecen al módulo de hotel
        // y le cargamos (eager loading) sus habitaciones ordenadas alfabéticamente.
        $zones = \Percy\Core\Models\Zone::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['rooms' => function ($query) {
                $query->orderBy('name'); // Ordenamos "101, 102, 103..."
            }])
            ->get();

        // 🌟 CASO DE RESPALDO: Por si hay habitaciones "huérfanas" (sin piso asignado aún)
        $unassignedRooms = \Percy\Core\Models\Room::where('tenant_id', $tenantId)
            ->whereNull('zone_id')
            ->orderBy('name')
            ->get();

        return [
            'zones' => $zones,
            'unassignedRooms' => $unassignedRooms,
        ];
    }

    // 🌟 NUEVO: LA ACCIÓN MÁGICA DEL CHECK-IN
    public function checkInAction(): Action
    {
        return Action::make('checkIn')
            ->label('Realizar Check-In')
            ->modalHeading(fn (array $arguments) => 'Check-In: ' . (Room::find($arguments['room_id'])?->name ?? 'Habitación'))
            ->modalDescription('Ingresa los datos del huésped para aperturar la cuenta.')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Registrar Ingreso')
            ->form([
                // 🌟 NUEVO SELECTOR DE CLIENTE (Idéntico a PosOrder)
                Forms\Components\Select::make('customer_id')
                    ->label('Huésped (Cliente)')
                    ->options(function () {
                        return \Percy\Core\Models\Customer::where('tenant_id', Auth::user()->tenant_id)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->manageOptionActions(function (\Filament\Forms\Components\Actions\Action $action) {
                        return $action
                            ->icon('heroicon-o-user-plus')
                            ->color('success')
                            ->tooltip('Agregar Cliente Nuevo');
                    })
                    ->createOptionModalHeading('Registrar Nuevo Cliente')
                    ->createOptionForm([
                        Forms\Components\Section::make('Identidad del Cliente')->schema([
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
                                ->maxLength(fn (\Filament\Forms\Get $get) => match ($get('document_type')) { 'DNI' => 8, 'RUC' => 11, default => 15 })
                                ->minLength(fn (\Filament\Forms\Get $get) => match ($get('document_type')) { 'DNI' => 8, 'RUC' => 11, default => null })
                                ->numeric(fn (\Filament\Forms\Get $get) => in_array($get('document_type'), ['DNI', 'RUC']))
                                ->required()
                                ->columnSpan(1)
                                ->suffixAction(
                                    \Filament\Forms\Components\Actions\Action::make('searchDecolecta')
                                        ->icon('heroicon-m-magnifying-glass')
                                        ->color('primary')
                                        ->tooltip('Buscar RUC en SUNAT')
                                        // 🌟 AQUÍ ESTÁ LA MAGIA: Solo visible si elige RUC
                                        ->visible(fn (\Filament\Forms\Get $get) => $get('document_type') === 'RUC')
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
                                                    \Filament\Notifications\Notification::make()->danger()->title('No encontrado')->body('El RUC no existe en SUNAT.')->send();
                                                }
                                            } catch (\Exception $e) {
                                                \Filament\Notifications\Notification::make()->danger()->title('Error de conexión')->body('No se pudo conectar con la API.')->send();
                                            }
                                        })
                                ),

                            Forms\Components\TextInput::make('name')
                                ->label('Nombre Completo o Razón Social')
                                ->required()
                                ->maxLength(150)
                                ->columnSpanFull(),
                        ])->columns(['default' => 1, 'sm' => 2]),

                        Forms\Components\Section::make('Datos de Contacto')->schema([
                            Forms\Components\TextInput::make('phone')->label('Teléfono')->tel()->maxLength(30)->columnSpan(1),
                            Forms\Components\TextInput::make('email')->label('Correo Electrónico')->email()->maxLength(150)->columnSpan(1),
                            Forms\Components\Textarea::make('address')->label('Dirección')->maxLength(255)->rows(2)->columnSpanFull(),
                        ])->columns(['default' => 1, 'sm' => 2])->collapsible(),
                    ])
                    ->createOptionUsing(function (array $data) {
                        $data['tenant_id'] = Auth::user()->tenant_id;
                        $customer = \Percy\Core\Models\Customer::create($data);
                        return $customer->id;
                    }),

                Forms\Components\DateTimePicker::make('expected_check_out_at')
                    ->label('Fecha y Hora Estimada de Salida')
                    ->default(now()->addDay()->setTime(12, 0))
                    ->required(),

                Forms\Components\TextInput::make('advance_payment')
                    ->label('Pago a Cuenta / Adelanto')
                    ->numeric()
                    ->prefix('S/')
                    ->default(0.00)
                    ->helperText('Opcional. Dinero que deja el huésped al ingresar.'),
            ])
            ->action(function (array $data, array $arguments) {
                $room = Room::find($arguments['room_id']);

                if (!$room || $room->status !== Room::STATUS_AVAILABLE) {
                    \Filament\Notifications\Notification::make()->danger()->title('La habitación no está disponible.')->send();
                    return;
                }

                \Percy\Core\Models\Reception::create([
                    'tenant_id' => Auth::user()->tenant_id,
                    'room_id' => $room->id,
                    'customer_id' => $data['customer_id'],
                    'user_id' => Auth::id(),
                    'check_in_at' => now(),
                    'expected_check_out_at' => $data['expected_check_out_at'],
                    'price_per_night' => $room->price_per_night,
                    'advance_payment' => $data['advance_payment'],
                    'status' => 'active',
                ]);

                $room->update(['status' => \Percy\Core\Models\Room::STATUS_OCCUPIED]);

                \Filament\Notifications\Notification::make()
                    ->title('¡Check-In exitoso!')
                    ->success()
                    ->send();
            });
    }

    // 🌟 ACCIÓN PARA AGREGAR PRODUCTOS A LA CUENTA
    public function addConsumptionAction(): Action
    {
        return Action::make('addConsumption')
            ->label('Agregar Consumo')
            ->modalHeading('Añadir Snacks / Frigobar a la cuenta')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Agregar Producto')
            ->form([
                Forms\Components\Select::make('product_id')
                    ->label('Seleccionar Producto')
                    // Traemos los productos del Tenant actual (Bebidas, snacks, etc)
                    ->options(\Percy\Core\Models\Product::where('tenant_id', Auth::user()->tenant_id)->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->live(onBlur: true)
                    // Autocompleta el precio cuando se elige el producto
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('unit_price', \Percy\Core\Models\Product::find($state)?->price ?? 0)),

                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('quantity')
                        ->label('Cantidad')
                        ->numeric()
                        ->default(1)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn ($state, Forms\Get $get, Forms\Set $set) => $set('total', $state * ($get('unit_price') ?? 0))),

                    Forms\Components\TextInput::make('unit_price')
                        ->label('Precio Unit. (S/)')
                        ->readonly()
                        ->numeric(),
                ]),
            ])
            ->action(function (array $data, array $arguments) {
                $room = Room::find($arguments['room_id']);

                // Buscamos la cuenta (recepción) activa de esta habitación
                $reception = \Percy\Core\Models\Reception::where('room_id', $room->id)->where('status', 'active')->first();
                $product = \Percy\Core\Models\Product::find($data['product_id']);

                $total = $data['quantity'] * $data['unit_price'];

                // Guardamos el detalle en la tabla que acabamos de crear
                \Percy\Core\Models\ReceptionItem::create([
                    'reception_id' => $reception->id,
                    'product_id' => $product->id,
                    'item_name' => $product->name,
                    'quantity' => $data['quantity'],
                    'unit_price' => $data['unit_price'],
                    'total' => $total,
                ]);

                // Actualizamos el contador de consumos totales en la recepción madre
                $reception->increment('consumptions_total', $total);

                \Filament\Notifications\Notification::make()->success()->title('Producto cargado a la habitación')->send();
            });
    }

    // 🌟 ACCIÓN: ADMINISTRAR CUENTA (Adelantos y ver consumos)
    public function manageAccountAction(): Action
    {
        return Action::make('manageAccount')
            ->label('Ver Cuenta')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('gray')
            ->modalHeading(fn (array $arguments) => 'Estado de Cuenta: ' . (Room::find($arguments['room_id'])?->name ?? 'Habitación'))
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Guardar')
            ->fillForm(function (array $arguments) {
                $room = Room::find($arguments['room_id']);
                $reception = \Percy\Core\Models\Reception::where('room_id', $room->id)->where('status', 'active')->first();
                return [
                    'advance_payment' => $reception?->advance_payment ?? 0,
                    'reception_id' => $reception?->id,
                ];
            })
            ->form([
                Forms\Components\Hidden::make('reception_id'),

                // Campo para modificar el adelanto
                Forms\Components\TextInput::make('advance_payment')
                    ->label('Adelantos / Abonos (S/)')
                    ->numeric()
                    ->prefix('S/')
                    ->helperText('Actualiza aquí si el huésped deja más dinero a cuenta.')
                    ->required(),

                // Tabla interactiva para ver y borrar consumos
                Forms\Components\Placeholder::make('consumptions_list')
                    ->label('Consumos Registrados (Frigobar/Snacks)')
                    ->content(function (Forms\Get $get) {
                        $receptionId = $get('reception_id');
                        if (!$receptionId) return '';

                        $items = \Percy\Core\Models\ReceptionItem::where('reception_id', $receptionId)->get();

                        if ($items->count() === 0) {
                            return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500 italic mt-2">No hay consumos registrados aún.</p>');
                        }

                        $html = '<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 w-full mt-2">';
                        $html .= '<table class="w-full text-xs text-left">';
                        $html .= '<thead class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white font-semibold"><tr><th class="px-3 py-2">Extra</th><th class="px-3 py-2 text-right">Monto</th><th class="px-3 py-2 text-center">Acción</th></tr></thead>';
                        $html .= '<tbody class="divide-y divide-gray-200 dark:divide-gray-700">';

                        foreach ($items as $item) {
                            $html .= '<tr class="bg-white dark:bg-gray-900">';
                            $html .= '<td class="px-3 py-2">'.number_format($item->quantity, 0).'x '.$item->item_name.'</td>';
                            $html .= '<td class="px-3 py-2 text-right font-bold text-gray-900 dark:text-gray-100">S/ '.number_format($item->total, 2).'</td>';
                            // El botón basurero que llama a nuestra función deleteExtra
                            $html .= '<td class="px-3 py-2 text-center">
                                        <button wire:click="deleteExtra('.$item->id.')" type="button" class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1 rounded-md hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                                            <svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                      </td>';
                            $html .= '</tr>';
                        }
                        $html .= '</tbody></table></div>';
                        $html .= '<p class="text-[10px] text-gray-500 mt-1">* Si eliminas un consumo, cierra este modal y vuelve a abrirlo para ver la lista actualizada.</p>';

                        return new \Illuminate\Support\HtmlString($html);
                    })
            ])
            ->action(function (array $data) {
                $reception = \Percy\Core\Models\Reception::find($data['reception_id']);
                if ($reception) {
                    $reception->update(['advance_payment' => $data['advance_payment']]);
                    \Filament\Notifications\Notification::make()->success()->title('Cuenta actualizada')->send();
                }
            });
    }

    // 🌟 ACCIÓN FINAL: CHECK-OUT Y COBRO (VERSIÓN BLINDADA Y SEGURA)
    public function checkoutAction(): Action
    {
        return Action::make('checkout')
            ->label('Cobrar (Check-Out)')

            // 🌟 1. EL BLINDAJE: Se ejecuta en el milisegundo en que haces clic, ANTES de abrir el modal
            ->before(function (Action $action) {
                $cajaAbierta = \Percy\Core\Models\CashRegister::where('user_id', Auth::id())
                    ->where('status', 'open')
                    ->first();

                if (!$cajaAbierta) {
                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('Caja Cerrada')
                        ->body('No puedes realizar cobros. Por favor, apertura tu Caja Chica primero para poder continuar.')
                        ->send();

                    // Esta es la magia de Filament: detiene el proceso y no abre el modal
                    $action->halt();
                }
            })

            ->modalHeading(fn (array $arguments) => 'Check-Out: ' . (Room::find($arguments['room_id'])?->name ?? 'Habitación'))
            ->modalDescription('Verifica los consumos, genera el comprobante y libera la habitación.')
            ->modalWidth('5xl')

            ->modalSubmitAction(fn (\Filament\Actions\StaticAction $action) => $action
                ->label('Confirmar Pago y Liberar Habitación')
                ->color('success')
                ->icon('heroicon-o-banknotes')
                ->extraAttributes(['class' => 'w-full [&>button]:w-full [&>button]:justify-center text-lg'])
            )
            ->modalCancelAction(fn (\Filament\Actions\StaticAction $action) => $action
                ->label('Cancelar')
                ->button()
                ->color('gray')
            )

            ->mountUsing(function (Forms\ComponentContainer $form, array $arguments) {
                $room = Room::find($arguments['room_id']);
                $reception = \Percy\Core\Models\Reception::where('room_id', $room->id)->where('status', 'active')->first();

                if (!$reception) return;

                $checkIn = \Carbon\Carbon::parse($reception->check_in_at);
                $nights = max(1, (int) ceil($checkIn->floatDiffInDays(now())));

                $hospedaje = $nights * $reception->price_per_night;
                $consumos = $reception->consumptions_total ?? 0;
                $adelanto = $reception->advance_payment ?? 0;
                $totalPagar = ($hospedaje + $consumos) - $adelanto;

                $form->fill([
                    'reception_id' => $reception->id,
                    'nights' => $nights,
                    'accommodation_total' => $hospedaje,
                    'consumptions_total' => $consumos,
                    'advance_payment' => $adelanto,
                    'total_to_pay' => max(0, $totalPagar),
                    'document_type' => '03',
                    'customer_id' => $reception->customer_id,
                ]);
            })

            ->form([
                Forms\Components\Hidden::make('reception_id'),

                // SECCIÓN 1: RESUMEN
                Forms\Components\Section::make('Resumen Financiero de la Cuenta')
                    ->schema([
                        Forms\Components\TextInput::make('nights')->label('Noches')->readonly()->numeric(),
                        Forms\Components\TextInput::make('accommodation_total')->label('Hospedaje')->readonly()->prefix('S/'),
                        Forms\Components\TextInput::make('consumptions_total')->label('Snacks / Extras')->readonly()->prefix('S/'),
                        Forms\Components\TextInput::make('advance_payment')->label('Garantía / Adelanto')->readonly()->prefix('S/'),
                        Forms\Components\TextInput::make('total_to_pay')
                            ->label('A COBRAR HOY')
                            ->readonly()
                            ->prefix('S/')
                            ->extraInputAttributes(['class' => 'text-xl font-black text-success-600 dark:text-success-400 bg-success-50 dark:bg-success-900/20']),
                    ])->columns(5),

                // SECCIÓN 2: GRID DIVIDIDO
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Section::make('Facturación y Pago')->schema([
                        Forms\Components\Select::make('document_type')
                            ->label('Tipo Comprobante')
                            ->options([
                                '03' => 'Boleta Electrónica',
                                '01' => 'Factura Electrónica',
                                '00' => 'Nota de Venta',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (\Filament\Forms\Set $set) {
                                $set('serie_documento', null);
                                $set('customer_id', null);
                            })
                            ->columnSpan(1),

                        Forms\Components\Select::make('serie_documento')
                            ->label('Serie')
                            ->options(function (\Filament\Forms\Get $get) {
                                return \Percy\Core\Models\Serie::where('tenant_id', Auth::user()->tenant_id)
                                    ->where('document_type', $get('document_type'))
                                    ->where('active', true)
                                    ->pluck('serie', 'serie');
                            })
                            ->required()->columnSpan(1),

                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente a Facturar')
                            ->options(function (\Filament\Forms\Get $get) {
                                $docType = $get('document_type');
                                $query = \Percy\Core\Models\Customer::where('tenant_id', Auth::user()->tenant_id);

                                if ($docType === '01') {
                                    $query->whereIn('document_type', ['RUC', '6']);
                                } else {
                                    $query->whereNotIn('document_type', ['RUC', '6']);
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(fn (\Filament\Forms\Get $get) => $get('document_type') === '01')
                            ->helperText(fn (\Filament\Forms\Get $get) => $get('document_type') === '01' ? 'Factura: Solo se muestran clientes con RUC.' : 'Boleta: Clientes con DNI/CE o déjalo vacío para Público General.')
                            ->manageOptionActions(function (\Filament\Forms\Components\Actions\Action $action) {
                                return $action->icon('heroicon-o-user-plus')->color('success')->tooltip('Agregar Cliente Nuevo');
                            })
                            ->createOptionModalHeading('Registrar Nuevo Cliente')
                            ->createOptionForm([
                                Forms\Components\Section::make('Identidad del Cliente')->schema([
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
                                        ->maxLength(fn (\Filament\Forms\Get $get) => match ($get('document_type')) { 'DNI' => 8, 'RUC' => 11, default => 15 })
                                        ->minLength(fn (\Filament\Forms\Get $get) => match ($get('document_type')) { 'DNI' => 8, 'RUC' => 11, default => null })
                                        ->numeric(fn (\Filament\Forms\Get $get) => in_array($get('document_type'), ['DNI', 'RUC']))
                                        ->required()
                                        ->columnSpan(1)
                                        ->suffixAction(
                                            \Filament\Forms\Components\Actions\Action::make('searchDecolecta')
                                                ->icon('heroicon-m-magnifying-glass')
                                                ->color('primary')
                                                ->tooltip('Buscar RUC en SUNAT')
                                                ->visible(fn (\Filament\Forms\Get $get) => $get('document_type') === 'RUC')
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
                                                            \Filament\Notifications\Notification::make()->danger()->title('No encontrado')->body('El RUC no existe en SUNAT.')->send();
                                                        }
                                                    } catch (\Exception $e) {
                                                        \Filament\Notifications\Notification::make()->danger()->title('Error de conexión')->body('No se pudo conectar con la API.')->send();
                                                    }
                                                })
                                        ),

                                    Forms\Components\TextInput::make('name')
                                        ->label('Nombre Completo o Razón Social')
                                        ->required()
                                        ->maxLength(150)
                                        ->columnSpanFull(),
                                ])->columns(['default' => 1, 'sm' => 2]),

                                Forms\Components\Section::make('Datos de Contacto')->schema([
                                    Forms\Components\TextInput::make('phone')->label('Teléfono')->tel()->maxLength(30)->columnSpan(1),
                                    Forms\Components\TextInput::make('email')->label('Correo Electrónico')->email()->maxLength(150)->columnSpan(1),
                                    Forms\Components\Textarea::make('address')->label('Dirección')->maxLength(255)->rows(2)->columnSpanFull(),
                                ])->columns(['default' => 1, 'sm' => 2])->collapsible(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $data['tenant_id'] = Auth::user()->tenant_id;
                                $customer = \Percy\Core\Models\Customer::create($data);
                                return $customer->id;
                            })
                            ->columnSpan(2),

                        Forms\Components\Select::make('payment_method')
                            ->label('Método de Pago')
                            ->options(\Percy\Core\Models\Sale::PAYMENT_METHODS)
                            ->default('Efectivo')
                            ->live()
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('N° Operación')
                            ->visible(fn (\Filament\Forms\Get $get) => \Percy\Core\Models\Sale::requiresReference($get('payment_method') ?? ''))
                            ->required(fn (\Filament\Forms\Get $get) => \Percy\Core\Models\Sale::requiresReference($get('payment_method') ?? ''))
                            ->columnSpan(1),
                    ])->columns(2)->columnSpan(2),

                    Forms\Components\Section::make('Detalle de Extras')->schema([
                        Forms\Components\Placeholder::make('detalle_snacks')
                            ->hiddenLabel()
                            ->content(function (Forms\Get $get) {
                                $receptionId = $get('reception_id');
                                if (!$receptionId) return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500">Sin consumos.</p>');

                                $items = \Percy\Core\Models\ReceptionItem::where('reception_id', $receptionId)->get();

                                if ($items->count() === 0) {
                                    return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500 italic mt-4 text-center">No se solicitaron extras.</p>');
                                }

                                $html = '<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 w-full mt-2">';
                                $html .= '<table class="w-full text-xs text-left">';
                                $html .= '<thead class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white font-semibold"><tr><th class="px-3 py-2">Extra</th><th class="px-3 py-2 text-right">Monto</th></tr></thead>';
                                $html .= '<tbody class="divide-y divide-gray-200 dark:divide-gray-700">';

                                foreach ($items as $item) {
                                    $html .= '<tr class="bg-white dark:bg-gray-900">';
                                    $html .= '<td class="px-3 py-2">'.number_format($item->quantity, 0).'x '.$item->item_name.'</td>';
                                    $html .= '<td class="px-3 py-2 text-right font-bold text-gray-900 dark:text-gray-100">S/ '.number_format($item->total, 2).'</td>';
                                    $html .= '</tr>';
                                }
                                $html .= '</tbody></table></div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                    ])->columnSpan(1),
                ]),
            ])
            ->action(function (array $data, array $arguments) {

                $room = Room::find($arguments['room_id']);
                $reception = \Percy\Core\Models\Reception::where('room_id', $room->id)->where('status', 'active')->first();

                $serieRecord = \Percy\Core\Models\Serie::where('tenant_id', Auth::user()->tenant_id)
                    ->where('document_type', $data['document_type'])
                    ->where('serie', $data['serie_documento'])
                    ->first();

                if (!$serieRecord) {
                    \Filament\Notifications\Notification::make()->danger()->title('Falta Serie')->body('La serie seleccionada no es válida.')->send();
                    return;
                }

                // 🌟 BLINDAJE 1: Iniciamos la transacción de BD. Si algo falla (ej. Stock), NADA se guarda.
                \Illuminate\Support\Facades\DB::beginTransaction();

                try {
                    // 1. Incrementar correlativo (SOLO UNA VEZ)
                    $serieRecord->increment('correlative');
                    $formatter = new \Luecano\NumeroALetras\NumeroALetras();
                    $legendText = $formatter->toInvoice($data['total_to_pay'], 2, 'SOLES');

                    // 2. Crear Venta Principal
                    $sale = \Percy\Core\Models\Sale::create([
                        'tenant_id' => Auth::user()->tenant_id,
                        'user_id' => Auth::id(),
                        'document_type' => $data['document_type'],
                        'series' => $serieRecord->serie,
                        'correlative' => $serieRecord->correlative,
                        'customer_id' => $data['customer_id'],
                        'status' => 'completed',
                        'sold_at' => now(),
                        'total' => $data['total_to_pay'],
                        'payment_method' => $data['payment_method'],
                        'payment_reference' => $data['payment_reference'] ?? null,
                        'legend_text' => $legendText,
                    ]);

                    /// 🌟 LÓGICA DE DETALLES Y SUNAT (TASA UNIFICADA + AGRUPACIÓN CONTABLE) 🌟
                    $afectacionGravadoDefecto = 1;
                    $totalGravadas = 0;
                    $totalIgv = 0;
                    $totalExoneradas = 0;

                    // 🌟 Obtenemos la tasa global del negocio (Ej: 10.5)
                    $tenant = \Percy\Core\Models\Tenant::find(Auth::user()->tenant_id);
                    $tenantIgv = $tenant ? (float) $tenant->igv_percentage : 18.0;
                    $factorDivisor = 1 + ($tenantIgv / 100);

                    // A. Hospedaje
                    if ($data['accommodation_total'] > 0) {
                        $precioHospedaje = (float) $data['accommodation_total'];

                        $baseHospedaje = round($precioHospedaje / $factorDivisor, 2);
                        $igvHospedaje = round($baseHospedaje * ($tenantIgv / 100), 2);

                        $totalGravadas += $baseHospedaje;
                        $totalIgv += $igvHospedaje;

                        \Percy\Core\Models\SaleItem::create([
                            'sale_id' => $sale->id,
                            'product_id' => null,
                            'item_name' => 'Servicio de Hospedaje - ' . $room->name . ' (' . $data['nights'] . ' noches)',
                            'quantity' => 1,
                            'unit_value' => $baseHospedaje,
                            'unit_price' => $precioHospedaje,
                            'total' => $precioHospedaje,
                            'igv_amount' => $igvHospedaje,
                            'tenant_id' => Auth::user()->tenant_id,
                            'afectacion_igv_id' => $afectacionGravadoDefecto,
                        ]);
                    }

                    // B. Consumos / Snacks Extras (STOCK Y SUNAT UNIFICADO)
                    $consumosExtras = \Percy\Core\Models\ReceptionItem::where('reception_id', $reception->id)->get();

                    foreach ($consumosExtras as $consumo) {
                        $productoOriginal = \Percy\Core\Models\Product::find($consumo->product_id);
                        $afectacionId = $productoOriginal ? $productoOriginal->afectacion_igv_id : $afectacionGravadoDefecto;

                        $afectacionDB = \Illuminate\Support\Facades\DB::table('afectaciones_igv')->find($afectacionId);
                        $esGravado = $afectacionDB ? ($afectacionDB->gravado == 1) : true;

                        $precioTotalSnack = (float) $consumo->total;
                        $precioUnitarioSnack = (float) $consumo->unit_price;
                        $cantidadSnack = $consumo->quantity;

                        if ($esGravado) {
                            // 🌟 Aplicamos la MISMA tasa global (Ej: 10.5%) a los snacks para que SUNAT no rechace
                            $baseTotalSnack = round($precioTotalSnack / $factorDivisor, 2);
                            $igvTotalSnack = round($baseTotalSnack * ($tenantIgv / 100), 2);

                            $valorUnitarioSnack = round($baseTotalSnack / $cantidadSnack, 5);

                            $totalGravadas += $baseTotalSnack;
                            $totalIgv += $igvTotalSnack;
                        } else {
                            $valorUnitarioSnack = $precioUnitarioSnack;
                            $igvTotalSnack = 0;
                            $totalExoneradas += $precioTotalSnack;
                        }

                        \Percy\Core\Models\SaleItem::create([
                            'sale_id' => $sale->id,

                            // 🌟 ESTO ES CLAVE: Le pasamos el ID real para que el Observer descuente el stock
                            'product_id' => $consumo->product_id,

                            // 🌟 TRUCO CONTABLE: Le agregamos "Consumo:" para justificar la tasa MYPE ante el contador
                            'item_name' => 'Consumo: ' . $consumo->item_name,

                            'quantity' => $cantidadSnack,
                            'unit_value' => $valorUnitarioSnack,
                            'unit_price' => $precioUnitarioSnack,
                            'total' => $precioTotalSnack,
                            'igv_amount' => $igvTotalSnack,
                            'tenant_id' => Auth::user()->tenant_id,
                            'afectacion_igv_id' => $afectacionId,
                        ]);
                    }

                    // 3. Actualizamos la Cabecera
                    $sale->update([
                        'op_gravadas' => $totalGravadas,
                        'igv' => $totalIgv,
                        'op_exoneradas' => $totalExoneradas,
                    ]);

                    // 4. Ingreso a Caja
                    $cajaAbierta = \Percy\Core\Models\CashRegister::where('user_id', Auth::id())
                        ->where('status', 'open')
                        ->first();

                    if ($cajaAbierta && $data['total_to_pay'] > 0) {
                        $cajaAbierta->addTransaction($data['total_to_pay']);
                    }

                    // 5. Cerrar Recepción
                    $reception->update([
                        'status' => 'completed',
                        'actual_check_out_at' => now(),
                        'accommodation_total' => $data['accommodation_total'],
                        'sale_id' => $sale->id,
                    ]);

                    $room->update(['status' => \Percy\Core\Models\Room::STATUS_DIRTY]);

                    // 🌟 Si llegamos hasta aquí sin que el Observer de Stock explote, GUARDAMOS en BD
                    \Illuminate\Support\Facades\DB::commit();

                    \Filament\Notifications\Notification::make()->success()->title('Check-Out Exitoso')->body('La habitación ahora requiere limpieza.')->send();

                    $ticketUrl = url('/print/ticket/' . $sale->id);
                    $this->js("
                        window.open('{$ticketUrl}', '_blank');
                        window.location.reload();
                    ");

                } catch (\Exception $e) {
                    // 🌟 BLINDAJE 2: El Observer gritó "No hay stock". Deshacemos TODO (Rollback).
                    \Illuminate\Support\Facades\DB::rollBack();

                    // Mostramos la alerta roja bonita en Filament
                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('Error al procesar la venta')
                        ->body($e->getMessage())
                        ->send();

                    return;
                }
            }); // <-- ¡Esta era la llave que te faltaba!
    }

    // 🌟 ACCIÓN RÁPIDA: Marcar como Disponible (Limpia)
    public function markAsAvailableAction(): Action
    {
        return Action::make('markAsAvailable')
            ->label('Marcar como Limpia')
            ->icon('heroicon-o-sparkles')
            ->color('success')
            ->requiresConfirmation() // Pide confirmación para evitar clics accidentales
            ->modalHeading('¿La habitación ya está limpia?')
            ->modalDescription('Esto cambiará el estado a "Disponible" y estará lista para un nuevo huésped.')
            ->modalSubmitActionLabel('Sí, marcar como Disponible')
            ->action(function (array $arguments) {
                $room = Room::find($arguments['room_id']);
                if ($room) {
                    $room->update(['status' => \Percy\Core\Models\Room::STATUS_AVAILABLE]);
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Habitación lista')
                        ->body("La habitación {$room->name} ya está disponible.")
                        ->send();
                }
            });
    }

    // 🌟 ACCIÓN RÁPIDA: Enviar a Mantenimiento
    public function markAsMaintenanceAction(): Action
    {
        return Action::make('markAsMaintenance')
            ->label('Enviar a Mantenimiento')
            ->icon('heroicon-o-wrench')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('¿Enviar habitación a mantenimiento?')
            ->modalDescription('La habitación no podrá ser alquilada hasta que se marque como disponible nuevamente.')
            ->modalSubmitActionLabel('Sí, enviar a mantenimiento')
            ->action(function (array $arguments) {
                $room = Room::find($arguments['room_id']);
                if ($room) {
                    $room->update(['status' => \Percy\Core\Models\Room::STATUS_MAINTENANCE]);
                    \Filament\Notifications\Notification::make()
                        ->warning()
                        ->title('Habitación en Mantenimiento')
                        ->body("La habitación {$room->name} está fuera de servicio.")
                        ->send();
                }
            });
    }

    // 🌟 FUNCIÓN PARA ELIMINAR UN SNACK POR ERROR
    public function deleteExtra($itemId)
    {
        $item = \Percy\Core\Models\ReceptionItem::find($itemId);
        if ($item) {
            $reception = $item->reception;
            $reception->decrement('consumptions_total', $item->total);
            $item->delete();

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Consumo eliminado')
                ->body('El producto fue retirado de la cuenta.')
                ->send();
        }
    }


}
