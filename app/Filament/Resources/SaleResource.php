<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers;
use Percy\Core\Models\Sale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->label('Cliente (Opcional)')
                            ->searchable()
                            ->preload(),

                        Forms\Components\DateTimePicker::make('sold_at')
                            ->label('Fecha y Hora')
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
                            ->required(),
                    ])->columns(3),

                    Forms\Components\Section::make('Detalle de Productos')->schema([
                        // EL REPEATER: Permite agregar múltiples filas (SaleItems)
                        Forms\Components\Repeater::make('items')
                            ->relationship() // Se conecta automáticamente a tu método items() en el modelo Sale
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->label('Producto')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('item_name')
                                    ->label('Descripción')
                                    ->required()
                                    ->columnSpan(3),

                                Forms\Components\Select::make('afectacion_igv_id')
                                    ->relationship('afectacionIgv', 'descripcion')
                                    ->label('Tipo IGV')
                                    ->default(1) // Gravado por defecto
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cant.')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('P. Unit (Inc IGV)')
                                    ->numeric()
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('unit_value')
                                    ->label('V. Unit (Sin IGV)')
                                    ->numeric()
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('igv_amount')
                                    ->label('IGV Total')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('total')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->required()
                                    ->columnSpan(2),
                            ])
                            ->columns(16) // Define el ancho de la fila para que quepan todos los campos
                            ->defaultItems(1)
                            ->addActionLabel('Agregar otro producto'),
                    ]),
                ])->columnSpan(['lg' => 3]), // Ocupa el 75% de la pantalla

                // PANEL DERECHO: TOTALES
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Resumen de Venta')->schema([
                        Forms\Components\TextInput::make('op_gravadas')
                            ->label('Op. Gravadas')
                            ->numeric()
                            ->default(0)
                            ->prefix('S/'),
                            
                        Forms\Components\TextInput::make('op_exoneradas')
                            ->label('Op. Exoneradas')
                            ->numeric()
                            ->default(0)
                            ->prefix('S/'),
                            
                        Forms\Components\TextInput::make('op_inafectas')
                            ->label('Op. Inafectas')
                            ->numeric()
                            ->default(0)
                            ->prefix('S/'),
                            
                        Forms\Components\TextInput::make('igv')
                            ->label('IGV (18%)')
                            ->numeric()
                            ->default(0)
                            ->prefix('S/'),
                            
                        Forms\Components\TextInput::make('total')
                            ->label('IMPORTE TOTAL')
                            ->numeric()
                            ->default(0)
                            ->prefix('S/')                        
                            ->required(),
                    ]),
                ])->columnSpan(['lg' => 1]), // Ocupa el 25% de la pantalla
            ])
            ->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('N° Venta')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('Público en General') // Si customer_id es null
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'canceled' => 'danger',
                    }),
                    
                Tables\Columns\TextColumn::make('sold_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                // Aquí luego agregaremos un filtro por fechas
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
