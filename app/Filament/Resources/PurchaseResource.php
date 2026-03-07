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
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 51;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Información de Compra')->schema([
                        Forms\Components\Select::make('supplier_id')
                            ->relationship('supplier', 'name')
                            ->label('Proveedor')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->required(),
                                Forms\Components\TextInput::make('ruc')
                                    ->label('RUC'),
                            ]),

                        Forms\Components\TextInput::make('document_number')
                            ->label('N° Documento')
                            ->placeholder('Ej: F001-123'),

                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Fecha de Compra')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pending' => 'Pendiente',
                                'completed' => 'Completado',
                                'canceled' => 'Cancelado',
                            ])
                            ->default('completed')
                            ->required(),
                    ])->columns(2),

                    Forms\Components\Section::make('Productos')->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set))
                            ->deleteAction(
                                fn (Forms\Components\Actions\Action $action) => $action->after(fn(Get $get, Set $set) => self::updateTotals($get, $set))
                            )
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->label('Producto')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            $set('unit_cost', $product->cost ?? 0);
                                        }
                                        self::updateRow($get, $set);
                                        self::updateTotals($get, $set);
                                    })
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Costo Unit.')
                                    ->numeric()
                                    ->required()
                                    ->prefix('S/')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => [self::updateRow($get, $set), self::updateTotals($get, $set)])
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->readonly()
                                    ->prefix('S/')
                                    ->columnSpan(3),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar Producto'),
                    ]),
                ])->columnSpan(['lg' => 3]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Resumen')->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->readonly()
                            ->prefix('S/'),

                        Forms\Components\TextInput::make('igv')
                            ->label('IGV (18%)')
                            ->numeric()
                            ->readonly()
                            ->prefix('S/'),

                        Forms\Components\TextInput::make('total')
                            ->label('TOTAL')
                            ->numeric()
                            ->readonly()
                            ->prefix('S/')
                            ->extraAttributes(['class' => 'font-bold']),
                    ]),

                    Forms\Components\Section::make('Notas')->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Observaciones')
                            ->rows(3),
                    ]),
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('N° Doc.')
                    ->searchable(),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'canceled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'completed' => 'Completado',
                        'canceled' => 'Cancelado',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
