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
use Percy\Core\Services\Tenants\TenantPlanService;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?string $navigationLabel = 'Kardex (Movimientos)';
    protected static ?string $modelLabel = 'Movimiento';
    protected static ?string $pluralModelLabel = 'Movimientos de Inventario';
    protected static ?int $navigationSort = 3;

    private static function userCanViewKardex(): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->tenant_id === null) {
            return false;
        }

        if (! $user->isAdmin()) {
            return false;
        }

        return app(TenantPlanService::class)->has('has_kardex', $user->tenant);
    }

    /**
     * Oculta el módulo de Reportes para el Súper Admin y para los Cajeros/Vendedores
     */
    public static function canViewAny(): bool
    {
        return self::userCanViewKardex();
    }

    // Solo mostramos los movimientos de la empresa actual
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if (! $user || $user->tenant_id === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where('tenant_id', $user->tenant_id)
            ->with([
                'product.unidadSunat',
                'batch',
                'user',
            ]);
    }

    // El Kardex es principalmente de auditoría, por ahora bloqueamos la creación manual directa aquí
    // (Luego podemos habilitarla para "Ajustes de inventario")
    public static function canCreate(): bool
    {
        return false;
    }

    // 🔒 NUEVOS CANDADOS EXTREMOS PARA KARDEX
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // El historial es inmutable
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    private static function formatInventoryQuantity(InventoryMovement $record, mixed $value, bool $withSign = false): string
    {
        $product = $record->product;

        if (! $product) {
            return (string) $value;
        }

        $numericValue = (float) $value;
        $absoluteValue = abs($numericValue);

        $sign = '';

        if ($withSign) {
            $sign = $record->type === 'IN' ? '+ ' : '- ';
        }

        if ($product->is_fractionable && $product->units_per_box > 0) {
            $boxes = floor($absoluteValue);
            $fraction = $absoluteValue - $boxes;
            $units = round($fraction * $product->units_per_box);

            $text = [];

            if ($boxes > 0) {
                $text[] = "{$boxes} Cajas";
            }

            if ($units > 0) {
                $text[] = "{$units} Und";
            }

            return $sign . (empty($text) ? '0 Und' : implode(' y ', $text));
        }

        if ($product->is_weighable) {
            $unitCode = $product->unidadSunat?->codigo ?? '';

            $suffix = match ($unitCode) {
                'KGM' => 'Kg',
                'LTR' => 'Lt',
                'GLL' => 'Gal',
                default => '',
            };

            return $sign . number_format($absoluteValue, 2) . " {$suffix}";
        }

        return $sign . number_format($absoluteValue, 0) . ' Und';
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Detalle del Movimiento')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->description('Historial inmutable del stock generado por compras, ventas o ajustes.')
                    ->schema([
                        TextEntry::make('product.name')
                            ->label('Producto')
                            ->weight('black')
                            ->icon('heroicon-o-cube')
                            ->columnSpanFull(),

                        TextEntry::make('batch.batch_number')
                            ->label('Lote')
                            ->badge()
                            ->color('gray')
                            ->placeholder('No aplica'),

                        TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => $state === 'IN' ? 'INGRESO' : 'SALIDA')
                            ->color(fn(string $state): string => $state === 'IN' ? 'success' : 'danger')
                            ->icon(
                                fn(string $state): string => $state === 'IN'
                                    ? 'heroicon-m-arrow-down-right'
                                    : 'heroicon-m-arrow-up-right'
                            ),

                        TextEntry::make('quantity')
                            ->label('Cantidad')
                            ->weight('black')
                            ->color(fn(InventoryMovement $record): string => $record->type === 'IN' ? 'success' : 'danger')
                            ->formatStateUsing(fn($state, InventoryMovement $record): string => self::formatInventoryQuantity($record, $state, true)),

                        TextEntry::make('balance_after')
                            ->label('Saldo posterior')
                            ->weight('black')
                            ->formatStateUsing(fn($state, InventoryMovement $record): string => self::formatInventoryQuantity($record, $state)),

                        TextEntry::make('reason')
                            ->label('Motivo')
                            ->placeholder('Sin motivo'),

                        TextEntry::make('user.name')
                            ->label('Usuario')
                            ->icon('heroicon-o-user')
                            ->placeholder('No registrado'),

                        TextEntry::make('created_at')
                            ->label('Fecha y hora')
                            ->dateTime('d/m/Y h:i A')
                            ->icon('heroicon-o-clock'),

                        TextEntry::make('notes')
                            ->label('Notas / Referencia')
                            ->placeholder('Sin notas')
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->recordUrl(null)
            ->recordAction('view')
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Movimiento')
                    ->state(fn(InventoryMovement $record): string => $record->product?->name ?? 'Producto no disponible')
                    ->description(function (InventoryMovement $record): string {
                        $type = $record->type === 'IN' ? 'Ingreso' : 'Salida';
                        $quantity = self::formatInventoryQuantity($record, $record->quantity, true);
                        $date = $record->created_at?->format('d/m/Y H:i') ?? 'Sin fecha';

                        return "{$type} · {$quantity} · {$date}";
                    })
                    ->icon(
                        fn(InventoryMovement $record): string => $record->type === 'IN'
                            ? 'heroicon-o-arrow-down-tray'
                            : 'heroicon-o-arrow-up-tray'
                    )
                    ->color(fn(InventoryMovement $record): string => $record->type === 'IN' ? 'success' : 'danger')
                    ->weight('black')
                    ->wrap()
                    ->searchable(['reason', 'notes'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->weight('bold')
                    ->limit(30)
                    ->visibleFrom('md'),

                // 🌟 LA MAGIA MULTI-TENANT: Columna exclusiva para Farmacias
                Tables\Columns\TextColumn::make('batch.batch_number')
                    ->label('Lote')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->visible(fn() => \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features['has_lots'] ?? false)
                    ->visibleFrom('xl'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => $state === 'IN' ? 'INGRESO' : 'SALIDA')
                    ->color(fn(string $state): string => $state === 'IN' ? 'success' : 'danger')
                    ->icon(fn(string $state): string => $state === 'IN' ? 'heroicon-m-arrow-down-right' : 'heroicon-m-arrow-up-right')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cant.')
                    ->sortable()
                    ->weight('black')
                    ->color(fn(InventoryMovement $record): string => $record->type === 'IN' ? 'success' : 'danger')
                    ->formatStateUsing(fn($state, InventoryMovement $record): string => self::formatInventoryQuantity($record, $state, true))
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo')
                    ->sortable()
                    ->description('Stock final')
                    ->formatStateUsing(fn($state, InventoryMovement $record): string => self::formatInventoryQuantity($record, $state))
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->searchable()
                    ->visibleFrom('lg'),

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
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver detalles')
                    ->tooltip('Ver detalles')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->color('info'),
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
