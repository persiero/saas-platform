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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Tables\Actions\Action;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Ventas';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id);
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
                            ->live() // Escucha el cambio
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Magia: Si cambia de Boleta a Factura, borramos la serie seleccionada
                                $set('series', null);
                            }),

                        Forms\Components\Select::make('series')
                            ->label('Serie')
                            ->required()
                            // Magia: Busca las series activas en la BD según el tipo de comprobante elegido
                            ->options(function (Get $get) {
                                $docType = $get('document_type');
                                if (!$docType) return [];

                                // Gracias a tu Súper Candado, esto SOLO trae las series de ESTE negocio
                                return \Percy\Core\Models\Serie::where('document_type', $docType)
                                    ->where('active', true)
                                    ->pluck('serie', 'serie');
                            }),

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
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\Select::make('payment_method')
                            ->label('Método de Pago')
                            ->options([
                                'Efectivo' => 'Efectivo',
                                'Yape' => 'Yape',
                                'Plin' => 'Plin',
                                'Transferencia' => 'Transferencia Bancaria',
                                'Tarjeta' => 'Tarjeta (POS)',
                            ])
                            ->default('Efectivo')
                            ->live() // Escucha los cambios en tiempo real
                            ->required()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('payment_reference', null)), // Limpia la referencia al cambiar

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('N° de Operación / Referencia')
                            ->placeholder('Ej: 123456')
                            // Se muestra SOLO si el método de pago NO es Efectivo
                            ->visible(fn (\Filament\Forms\Get $get) => $get('payment_method') !== 'Efectivo' && $get('payment_method') !== null)
                            // Es obligatorio SOLO si no es Efectivo
                            ->required(fn (\Filament\Forms\Get $get) => $get('payment_method') !== 'Efectivo'),

                        Forms\Components\DateTimePicker::make('sold_at')
                            ->label('Fecha de Emisión')
                            ->default(now())
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
                                    ->columnSpan(4) // Más ancho
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            // Llenamos campos visuales
                                            $set('unit_price', $product->price);
                                            $set('afectacion_igv_id', $product->afectacion_igv_id);
                                            // Llenamos campos ocultos
                                            $set('item_name', $product->name);
                                        }
                                        self::updateRow($get, $set);
                                        self::updateTotals($get, $set);
                                    }),

                                Forms\Components\Select::make('afectacion_igv_id')
                                    ->relationship('afectacionIgv', 'descripcion')
                                    ->label('Tipo IGV')
                                    ->required()
                                    ->columnSpan(2)
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)]),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cant.')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(2) // Ahora tiene un tamaño excelente
                                    ->live(onBlur: true) // Se actualiza al hacer clic fuera de la caja
                                    ->afterStateUpdated(fn(Get $get, Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)]),

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
                                Forms\Components\Hidden::make('item_name'),
                                Forms\Components\Hidden::make('unit_value'),
                                Forms\Components\Hidden::make('igv_amount'),
                            ])
                            ->columns(12) // Pasamos de 16 a 12 columnas. ¡Diseño perfecto!
                            ->defaultItems(1)
                            ->addActionLabel('Agregar otro producto'),
                    ]),
                ])->columnSpan(['lg' => 3]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Resumen de Venta')->schema([
                        Forms\Components\TextInput::make('op_gravadas')
                            ->label('Op. Gravadas')
                            ->numeric()
                            ->default(0)
                            ->readonly()
                            ->prefix('S/'),

                        Forms\Components\TextInput::make('op_exoneradas')
                            ->label('Op. Exoneradas')
                            ->numeric()
                            ->default(0)
                            ->readonly()
                            ->prefix('S/'),

                        Forms\Components\TextInput::make('op_inafectas')
                            ->label('Op. Inafectas')
                            ->numeric()
                            ->default(0)
                            ->readonly()
                            ->prefix('S/'),

                        Forms\Components\TextInput::make('igv')
                            ->label('IGV (18%)')
                            ->numeric()
                            ->default(0)
                            ->readonly()
                            ->prefix('S/'),

                        Forms\Components\TextInput::make('total')
                            ->label('IMPORTE TOTAL')
                            ->numeric()
                            ->default(0)
                            ->readonly()
                            ->prefix('S/')
                            ->required(),
                    ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            // 1. Identificadores
            Tables\Columns\TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('document_number')
                ->label('Comprobante')
                ->state(fn (Sale $record): string => "{$record->series}-{$record->correlative}")
                ->searchable(['series', 'correlative'])
                ->sortable()
                ->weight('bold')
                // NUEVO: Esto mostrará un texto pequeño debajo del número de la Nota
                ->description(fn (Sale $record): ?string =>
                    in_array($record->document_type, ['07', '08'])
                        ? "Ref: {$record->affected_document_series}-{$record->affected_document_correlative}"
                        : null
                )
                ->color(fn (Sale $record): string => match ($record->document_type) {
                    '07' => 'danger',  // Crédito (Rojo)
                    '08' => 'warning', // Débito (Amarillo)
                    default => 'primary', // Facturas/Boletas normales
                }),

            // 2. Información del Cliente y Venta
            Tables\Columns\TextColumn::make('customer.name')
                ->label('Cliente')
                ->placeholder('Público en General')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('total')
                ->label('Total')
                ->money('PEN')
                ->sortable()
                ->alignment('right'),

            // 3. Estados (Interno y SUNAT)
            Tables\Columns\TextColumn::make('status')
                ->label('Venta')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'completed' => 'success',
                    'pending' => 'warning',
                    'canceled' => 'danger',
                    default => 'gray',
                }),

            Tables\Columns\TextColumn::make('sunat_status')
                ->label('Estado SUNAT')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'accepted' => 'success',
                    'pending' => 'warning',
                    'rejected' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'accepted' => 'ACEPTADO',
                    'pending' => 'PENDIENTE',
                    'rejected' => 'RECHAZADO',
                    default => strtoupper($state),
                })
                ->description(fn (Sale $record): ?string => $record->sunat_description),

            Tables\Columns\TextColumn::make('sold_at')
                ->label('Fecha')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ])
        ->filters([
            // Filtros de fecha o tipo pueden ir aquí después
        ])
        ->actions([
            // GRUPO 1: Acciones Principales (Envío y Ticket)
            Tables\Actions\Action::make('sendToSunat')
                ->label('Enviar a SUNAT')
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
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Error Crítico')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Tables\Actions\Action::make('print')
                ->label('Ticket')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn (Sale $record): string => route('sales.ticket', $record))
                ->openUrlInNewTab(),

            // GRUPO 2: Archivos Digitales
            Tables\Actions\ActionGroup::make([
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
            ])
            ->label('SUNAT')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('gray'),

            Tables\Actions\Action::make('anularVenta')
                ->label('Generar Nota de Crédito')
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
                        $nota = $record->replicate([
                            'sunat_status', 'sunat_code', 'sunat_description', 'sunat_hash',
                            'sunat_xml_path', 'sunat_cdr_path', 'sunat_pdf_path', 'legend_text'
                        ]);

                        // 2. Le asignamos su nueva identidad como Nota de Crédito
                        //$nota->document_type = '07';
                        //$nota->series = $data['serie_nota'];

                        // --- NUEVA LÓGICA DE CORRELATIVO AUTOMÁTICO ---
                        $serieConfig = \Percy\Core\Models\Serie::where('document_type', '07')
                            ->where('serie', $data['serie_nota'])
                            ->first();

                        if (!$serieConfig) {
                            throw new \Exception("Debe registrar la serie {$data['serie_nota']} en Configuración primero.");
                        }

                        $serieConfig->increment('correlative'); // +1 al contador de tu tabla series

                        $nota = $record->replicate([/* ... campos a omitir ... */]);
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
                            // Opcional: Cambiar el estado interno del documento original a "Anulado"
                            $record->update(['status' => 'canceled']);

                            \Filament\Notifications\Notification::make()
                                ->title('Nota de Crédito Aceptada')
                                ->body('Se anuló el comprobante correctamente.')
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
                ->label('Generar Nota de Débito')
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
                        ->relationship('items.product', 'name') // O usa una consulta directa a Product::class si falla
                        ->options(\Percy\Core\Models\Product::pluck('name', 'id'))
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
                        // 1. Clonamos la venta original limpia de estados
                        $nota = $record->replicate([
                            'sunat_status', 'sunat_code', 'sunat_description', 'sunat_hash',
                            'sunat_xml_path', 'sunat_cdr_path', 'sunat_pdf_path', 'legend_text'
                        ]);

                        // 2. Le asignamos su identidad como Nota de Débito
                        //$nota->document_type = '08'; // Código SUNAT para Débito
                        //$nota->series = $data['serie_nota'];

                        // --- NUEVA LÓGICA DE CORRELATIVO AUTOMÁTICO ---
                        $serieConfig = \Percy\Core\Models\Serie::where('document_type', '08')
                            ->where('serie', $data['serie_nota'])
                            ->first();

                        if (!$serieConfig) {
                            throw new \Exception("Debe registrar la serie {$data['serie_nota']} en Configuración primero.");
                        }

                        $serieConfig->increment('correlative');

                        $nota = $record->replicate([/* ... */]);
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

            // GRUPO 3: Acciones Estándar
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
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
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
