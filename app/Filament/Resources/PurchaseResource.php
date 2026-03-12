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

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Compras';
    protected static ?string $modelLabel = 'Compra';
    protected static ?string $pluralModelLabel = 'Compras';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 51;

    public static function canViewAny(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        // Bloquea a los cajeros, permite el paso a los Admins (tanto al Súper Admin como al Dueño local)
        return $user->isAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Datos del Proveedor')
                        ->icon('heroicon-o-building-office-2')
                        ->schema([
                            Forms\Components\Select::make('supplier_id')
                                ->relationship('supplier', 'name', fn (Builder $query) => $query->where('active', true))
                                ->label('Proveedor')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->helperText('Selecciona el proveedor de esta compra')
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Nombre / Razón Social')
                                        ->required(),
                                    Forms\Components\TextInput::make('ruc')
                                        ->label('RUC')
                                        ->length(11),
                                    Forms\Components\Toggle::make('active')
                                        ->label('Activo')
                                        ->default(true),
                                ])
                                ->createOptionModalHeading('Registrar Nuevo Proveedor')
                                ->columnSpanFull(),
                        ]),

                        Forms\Components\Section::make('Detalles del Documento')
                            ->icon('heroicon-o-document-text')
                            ->columns(3) // Dividir en 3 columnas iguales
                            ->schema([
                                Forms\Components\TextInput::make('document_number')
                                    ->label('N° de Documento')
                                    ->placeholder('Ej: F001-00123')
                                    ->helperText('Número de factura o comprobante')
                                    ->prefixIcon('heroicon-o-hashtag')
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('purchase_date')
                                    ->label('Fecha de Compra')
                                    ->default(now())
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now())
                                    ->helperText('Fecha de emisión del documento')
                                    ->prefixIcon('heroicon-o-calendar')
                                    ->columnSpan(1),

                                Forms\Components\Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'pending' => 'Pendiente',
                                        'completed' => 'Completado',
                                        'canceled' => 'Cancelado',
                                    ])
                                    ->default('completed')
                                    ->required()
                                    ->native(false)
                                    ->helperText('Estado actual de la compra')
                                    ->columnSpan(1),
                            ]),

                    Forms\Components\Section::make('Detalle de Productos')
                        ->description('Agrega los productos comprados')
                        ->icon('heroicon-o-cube')
                        ->schema([
                            Forms\Components\Repeater::make('items')
                                ->relationship()
                                ->label('')
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set))
                                ->deleteAction(
                                    fn (Forms\Components\Actions\Action $action) => $action
                                        ->after(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                                        ->requiresConfirmation()
                                        ->modalHeading('Eliminar Producto')
                                        ->modalDescription('¿Estás seguro de eliminar este producto?')
                                )
                                ->schema([
                                    Forms\Components\Select::make('product_id')
                                        ->relationship('product', 'name')
                                        ->label('Producto')
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->live()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            if ($state) {
                                                $product = Product::find($state);
                                                $set('unit_cost', $product->cost ?? 0);
                                                // Magia secreta: detectamos si es fraccionable para saber si pedimos lote
                                                $set('_is_fractionable', $product->is_fractionable);
                                            }
                                            self::updateRow($get, $set);
                                            self::updateTotals($get, $set);
                                        })
                                        ->columnSpan(4),

                                    // 🌟 NUEVO: NÚMERO DE LOTE (Solo para Farmacias)
                                    Forms\Components\TextInput::make('batch_number')
                                        ->label('N° de Lote')
                                        ->visible(function () {
                                            $sector = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->name ?? '';
                                            return str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica');
                                        })
                                        ->required(function () {
                                            $sector = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->name ?? '';
                                            return str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica');
                                        })
                                        ->columnSpan(2),

                                    // 🌟 NUEVO: FECHA DE VENCIMIENTO
                                    Forms\Components\DatePicker::make('expiration_date')
                                        ->label('Vencimiento')
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->visible(function () {
                                            $sector = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->name ?? '';
                                            return str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica');
                                        })
                                        ->required(function () {
                                            $sector = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->name ?? '';
                                            return str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica');
                                        })
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('quantity')
                                        ->label('Cantidad')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn(Get $get, Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)])
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make('unit_cost')
                                        ->label('Costo Unitario')
                                        ->numeric()
                                        ->required()
                                        ->prefix('S/')
                                        ->step(0.01)
                                        ->minValue(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn(Get $get, Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)])
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('subtotal')
                                        ->label('Subtotal')
                                        ->numeric()
                                        ->readonly()
                                        ->prefix('S/')
                                        ->dehydrated()
                                        ->columnSpan(2),
                                ])
                                ->columns(12)
                                ->defaultItems(1)
                                ->addActionLabel('+ Agregar Producto')
                                ->reorderableWithButtons()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string =>
                                    $state['product_id'] ? Product::find($state['product_id'])?->name : 'Nuevo producto'
                                ),
                        ]),
                ])->columnSpan(['lg' => 3]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Resumen Financiero')
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Forms\Components\Placeholder::make('subtotal_label')
                                ->label('Subtotal (Op. Gravadas)')
                                ->content(fn (Get $get): string => 'S/ ' . number_format((float)($get('subtotal') ?? 0), 2))
                                ->extraAttributes(['class' => 'flex justify-between border-b pb-2']),

                            Forms\Components\Placeholder::make('igv_label')
                                ->label('IGV (18%)')
                                ->content(fn (Get $get): string => 'S/ ' . number_format((float)($get('igv') ?? 0), 2))
                                ->extraAttributes(['class' => 'flex justify-between border-b pb-2']),

                            Forms\Components\Placeholder::make('total_label')
                                ->label('TOTAL A PAGAR')
                                ->content(fn (Get $get): string => 'S/ ' . number_format((float)($get('total') ?? 0), 2))
                                // Estilo destacado para el total
                                ->extraAttributes(['class' => 'flex justify-between text-2xl font-black text-primary-600 pt-2']),

                            Forms\Components\Hidden::make('subtotal'),
                            Forms\Components\Hidden::make('igv'),
                            Forms\Components\Hidden::make('total'),
                        ]),

                    Forms\Components\Section::make('Notas Adicionales')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\Textarea::make('notes')
                                ->label('Observaciones')
                                ->rows(4)
                                ->placeholder('Agrega notas o comentarios sobre esta compra...')
                                ->helperText('Información adicional relevante'),
                        ])->collapsible(),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(4);
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
                    ->description(fn (Purchase $record): string => $record->purchase_date->diffForHumans()),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-building-office-2')
                    ->weight('bold')
                    ->description(fn (Purchase $record): ?string => $record->document_number ? "Doc: {$record->document_number}" : null),

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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'completed' => 'Completado',
                        'canceled' => 'Cancelado',
                        default => $state,
                    })

                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
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
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('purchase_date', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('purchase_date', '<=', $date));
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
                        ->label('Ver')
                        ->icon('heroicon-o-eye')
                        ->modalHeading('Detalles de la Compra')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar'),
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil'),
                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Compra')
                        ->modalDescription('¿Estás seguro de que deseas eliminar esta compra?')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->modalCancelActionLabel('Cancelar'),
                ])->label('Acciones')
                  ->icon('heroicon-o-ellipsis-vertical')
                  ->button()
                  ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Compras')
                        ->modalDescription('¿Estás seguro de que deseas eliminar las compras seleccionadas?')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->modalCancelActionLabel('Cancelar'),
                ]),
            ])
            ->emptyStateHeading('No hay compras registradas')
            ->emptyStateDescription('Comienza registrando tu primera compra usando el botón de arriba.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }

    public static function updateRow(Get $get, Set $set): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $unitCost = (float) ($get('unit_cost') ?? 0);
        $subtotal = $quantity * $unitCost;

        $set('subtotal', round($subtotal, 2));
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

        $subtotal = 0;
        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $cost = (float) ($item['unit_cost'] ?? 0);
            $subtotal += $qty * $cost;
        }

        $igv = $subtotal * 0.18;
        $total = $subtotal + $igv;

        $set($prefix . 'subtotal', round($subtotal, 2));
        $set($prefix . 'igv', round($igv, 2));
        $set($prefix . 'total', round($total, 2));
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
