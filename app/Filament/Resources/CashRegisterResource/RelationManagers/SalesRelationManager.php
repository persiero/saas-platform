<?php

namespace App\Filament\Resources\CashRegisterResource\RelationManagers;

use App\Filament\Resources\SaleResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Percy\Core\Models\Sale;

class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'sales';

    protected static ?string $title = 'Ventas Asociadas';

    protected static ?string $modelLabel = 'Venta';

    protected static ?string $pluralModelLabel = 'Ventas';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['customer', 'user']))
            ->striped()
            ->paginated([5, 10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Venta')
                    ->state(function (Sale $record): string {
                        $series = $record->series ?: 'WEB';
                        $correlative = $record->correlative
                            ? str_pad((string) $record->correlative, 6, '0', STR_PAD_LEFT)
                            : 'Pendiente';

                        return "{$series}-{$correlative}";
                    })
                    ->description(function (Sale $record): string {
                        $cliente = $record->customer?->name ?? 'Público en General';
                        $total = 'S/ ' . number_format((float) $record->total, 2);
                        $fecha = $record->sold_at?->format('d/m/Y H:i') ?? 'Sin fecha';

                        return "{$cliente} · {$total} · {$fecha}";
                    })
                    ->icon('heroicon-o-receipt-percent')
                    ->weight('black')
                    ->wrap()
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('series')
                    ->label('Serie')
                    ->weight('bold')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('correlative')
                    ->label('Número')
                    ->formatStateUsing(fn($state) => str_pad((string) $state, 6, '0', STR_PAD_LEFT))
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('Público en General')
                    ->searchable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Atendido por')
                    ->placeholder('No asignado')
                    ->visibleFrom('xl'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Pago')
                    ->badge()
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->weight('bold')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'completed' => 'Completado',
                        'pending_payment' => 'Pendiente',
                        'canceled' => 'Anulado',
                        default => ucfirst($state),
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending_payment' => 'warning',
                        'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('sold_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visibleFrom('xl'),
            ])
            ->defaultSort('sold_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('viewSale')
                    ->label('Ver venta')
                    ->tooltip('Ver venta')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->color('info')
                    ->url(fn(Sale $record) => SaleResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }
}
