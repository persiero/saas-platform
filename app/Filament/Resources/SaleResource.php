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
                            //->label('Correlativo')
                            //->numeric()
                            //->required()
                            //->default(1), // Por ahora lo ingresamos manual, luego lo automatizaremos

                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->label('Cliente')
                            ->searchable()
                            ->preload()
                            ->columnSpan(2), // Que ocupe más espacio visual

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
                    ])->columns(4), // Dividimos esta sección en 4 columnas

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
                ->weight('bold'),

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
                ->label('Enviar')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Sale $record) => 
                    in_array($record->document_type, ['01', '03']) && 
                    $record->status !== 'canceled' && 
                    $record->sunat_status !== 'accepted'
                )
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
