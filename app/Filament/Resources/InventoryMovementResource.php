<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryMovementResource\Pages;
use App\Filament\Resources\InventoryMovementResource\RelationManagers;
use Percy\Core\Models\InventoryMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Kardex (Movimientos)';
    protected static ?string $modelLabel = 'Movimiento';
    protected static ?string $pluralModelLabel = 'Movimientos de Inventario';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 30;

    // Solo mostramos los movimientos de la empresa actual
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', Auth::user()->tenant_id);
    }

    // El Kardex es principalmente de auditoría, por ahora bloqueamos la creación manual directa aquí
    // (Luego podemos habilitarla para "Ajustes de inventario")
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalle del Movimiento')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->relationship('product', 'name')
                            ->label('Producto')
                            ->disabled(),

                        Forms\Components\TextInput::make('type')
                            ->label('Tipo de Movimiento')
                            ->disabled(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantidad')
                            ->disabled(),

                        Forms\Components\TextInput::make('balance_after')
                            ->label('Saldo Posterior')
                            ->disabled(),

                        Forms\Components\TextInput::make('reason')
                            ->label('Motivo')
                            ->disabled(),

                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label('Realizado por')
                            ->disabled(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas / Referencia')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->weight('bold')
                    ->limit(30),

                // 🌟 LA MAGIA MULTI-TENANT: Columna exclusiva para Farmacias
                Tables\Columns\TextColumn::make('batch.batch_number')
                    ->label('Lote')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->visible(function () {
                        $sector = Auth::user()->tenant->businessSector->name ?? '';
                        return str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica');
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'IN' ? 'INGRESO' : 'SALIDA')
                    ->color(fn (string $state): string => $state === 'IN' ? 'success' : 'danger')
                    ->icon(fn (string $state): string => $state === 'IN' ? 'heroicon-m-arrow-down-right' : 'heroicon-m-arrow-up-right'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cant.')
                    ->numeric()
                    ->sortable()
                    ->weight('black')
                    ->color(fn ($record) => $record->type === 'IN' ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state, $record) => ($record->type === 'IN' ? '+' : '-') . $state),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo')
                    ->numeric()
                    ->sortable()
                    ->description('Stock final'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->icon('heroicon-m-user-circle')
                    ->toggleable(isToggledHiddenByDefault: true), // Oculto por defecto para no saturar la tabla
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo de Movimiento')
                    ->options([
                        'IN' => 'Ingresos',
                        'OUT' => 'Salidas',
                    ]),

                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Filtrar por Producto')
                    ->relationship('product', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Ver'),
            ])
            ->bulkActions([
                // En un Kardex estricto, no se debe permitir eliminar movimientos en bloque
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageInventoryMovements::route('/'),
        ];
    }
}
