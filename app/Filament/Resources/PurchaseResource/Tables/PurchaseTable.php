<?php

namespace App\Filament\Resources\PurchaseResource\Tables;

use App\Filament\Resources\PurchaseResource\Actions\PurchaseTableActions;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Percy\Core\Models\Purchase;

class PurchaseTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->defaultSort('purchase_date', 'desc')
            ->filters(self::filters())
            ->actions([
                Tables\Actions\ActionGroup::make(PurchaseTableActions::actions())
                    ->label('Acciones')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make()
                ]),
            ])
            ->emptyStateHeading('No hay compras registradas')
            ->emptyStateDescription('Comienza registrando tu primera compra usando el botón de arriba.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }

    private static function columns(): array
    {
        return [
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
                ->description(fn(Purchase $record): string => $record->purchase_date->diffForHumans()),

            Tables\Columns\TextColumn::make('supplier.name')
                ->label('Proveedor')
                ->searchable()
                ->sortable()
                ->icon('heroicon-o-building-office-2')
                ->weight('bold')
                ->description(
                    fn(Purchase $record): ?string =>
                    $record->document_number ? "Doc: {$record->document_number}" : null
                ),

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
                ->formatStateUsing(fn(string $state): string => match ($state) {
                    'pending' => 'Pendiente',
                    'completed' => 'Completado',
                    'canceled' => 'Cancelado',
                    default => $state,
                })
                ->color(fn(string $state): string => match ($state) {
                    'pending' => 'warning',
                    'completed' => 'success',
                    'canceled' => 'danger',
                    default => 'gray',
                })
                ->icon(fn(string $state): string => match ($state) {
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
        ];
    }

    private static function filters(): array
    {
        return [
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
                ->columns(2)
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['from'],
                            fn(Builder $query, $date) => $query->whereDate('purchase_date', '>=', $date)
                        )
                        ->when(
                            $data['until'],
                            fn(Builder $query, $date) => $query->whereDate('purchase_date', '<=', $date)
                        );
                })
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if ($data['from'] ?? null) {
                        $indicators[] = 'Desde: ' . Carbon::parse($data['from'])->format('d/m/Y');
                    }

                    if ($data['until'] ?? null) {
                        $indicators[] = 'Hasta: ' . Carbon::parse($data['until'])->format('d/m/Y');
                    }

                    return $indicators;
                }),
        ];
    }
}
