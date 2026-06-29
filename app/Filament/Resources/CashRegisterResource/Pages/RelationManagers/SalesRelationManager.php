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
            ->columns([
                Tables\Columns\TextColumn::make('series')
                    ->label('Serie')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('correlative')
                    ->label('Número')
                    ->formatStateUsing(fn ($state) => str_pad((string) $state, 6, '0', STR_PAD_LEFT)),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('Público en General')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Atendido por')
                    ->placeholder('No asignado'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Pago')
                    ->badge(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Completado',
                        'pending_payment' => 'Pendiente',
                        'canceled' => 'Anulado',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending_payment' => 'warning',
                        'canceled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('sold_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('sold_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('viewSale')
                    ->label('Ver venta')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Sale $record) => SaleResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }
}
