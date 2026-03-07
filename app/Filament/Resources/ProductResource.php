<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use Percy\Core\Models\Product;
use Percy\Core\Models\InventoryMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Productos';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?int $navigationSort = 22;

    // Filtro global para asegurar que solo se vean productos del tenant actual
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Información Principal')->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Producto / Servicio')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\Select::make('unit_code')
                            ->label('Unidad de Medida (SUNAT)')
                            ->options([
                                'NIU' => 'Unidad (Bienes)',
                                'ZZ'  => 'Servicio (Atención, Delivery, etc.)',
                                'KGM' => 'Kilogramos',
                                'LTR' => 'Litros',
                            ])
                            ->default('NIU')
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->label('Tipo de Ítem')
                            ->options([
                                'product' => 'Producto Físico',
                                'service' => 'Servicio',
                            ])
                            ->required()
                            ->default('product'),

                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name') // Solo mostrará categorías de ESTE tenant
                            ->label('Categoría')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('description')
                            ->label('Descripción')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),
                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Precios e Impuestos')->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Precio de Venta')
                            ->numeric()
                            ->prefix('S/')
                            ->required(),

                        Forms\Components\TextInput::make('cost')
                            ->label('Costo Referencial')
                            ->numeric()
                            ->prefix('S/')
                            ->default(0),

                        Forms\Components\Select::make('afectacion_igv_id')
                            ->relationship('afectacionIgv', 'descripcion')
                            ->label('Afectación IGV')
                            ->required()
                            ->default(1) // Por defecto Gravado (Id 1)
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('unidad_sunat_id')
                            ->relationship('unidadSunat', 'descripcion')
                            ->label('Unidad de Medida (SUNAT)')
                            ->required()
                            ->default(1) // Por defecto NIU (Id 1)
                            ->searchable()
                            ->preload(),
                    ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3); // Divide la pantalla en 3 columnas para un diseño más pro
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('PEN') // Formato de moneda peruana
                    ->sortable(),

                Tables\Columns\TextColumn::make('unidadSunat.codigo')
                    ->label('Unidad')
                    ->badge(), // Lo muestra como una etiqueta bonita

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock Actual')
                    ->numeric()
                    ->badge()
                    ->state(function (Product $record): float {
                        // Forzamos recarga fresca de movimientos
                        $record->refresh();
                        return $record->current_stock;
                    })
                    ->color(fn (float $state): string => match (true) {
                        $state <= 5 => 'danger',
                        $state <= 15 => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('add_stock')
                    ->label('Ajustar Stock')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Tipo de Movimiento')
                            ->options([
                                'IN' => 'Ingreso (Compra, Devolución)',
                                'OUT' => 'Salida (Merma, Vencimiento)',
                            ])
                            ->required()
                            ->default('IN')
                            ->live(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantidad')
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->live(),

                        Forms\Components\Placeholder::make('stock_warning')
                            ->label('')
                            ->content(function (Get $get, $record) {
                                if ($get('type') === 'OUT' && $get('quantity')) {
                                    $stockActual = $record->current_stock;
                                    $cantidad = (float) $get('quantity');
                                    $stockFinal = $stockActual - $cantidad;

                                    if ($stockFinal < 0) {
                                        return "⚠️ Stock insuficiente. Actual: {$stockActual} | Quedaría: {$stockFinal}";
                                    }
                                    return "✓ Stock actual: {$stockActual} | Quedaría: {$stockFinal}";
                                }
                                return '';
                            })
                            ->visible(fn (Get $get) => $get('type') === 'OUT'),

                        Forms\Components\TextInput::make('reason')
                            ->label('Motivo / Razón')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Ingreso inicial, Lote vencido...'),
                    ])
                    ->action(function (array $data, $record) {
                        // Validar stock antes de crear salida
                        if ($data['type'] === 'OUT') {
                            $stockActual = $record->current_stock;
                            if ($stockActual < $data['quantity']) {
                                Notification::make()
                                    ->title('Stock Insuficiente')
                                    ->body("No se puede realizar la salida. Stock actual: {$stockActual}")
                                    ->danger()
                                    ->send();
                                return;
                            }
                        }

                        InventoryMovement::create([
                            'tenant_id' => \Illuminate\Support\Facades\Auth::user()->tenant_id,
                            'product_id' => $record->id,
                            'type' => $data['type'],
                            'quantity' => $data['quantity'],
                            'reason' => $data['reason'],
                        ]);

                        Notification::make()
                            ->title('Movimiento Registrado')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Registrar Movimiento de Inventario'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
