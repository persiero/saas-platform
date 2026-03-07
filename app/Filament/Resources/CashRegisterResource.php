<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashRegisterResource\Pages;
use Percy\Core\Models\CashRegister;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashRegisterResource extends Resource
{
    protected static ?string $model = CashRegister::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Caja';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?int $navigationSort = 30;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    Forms\Components\TextInput::make('opening_amount')
                        ->label('Monto de Apertura')
                        ->required()
                        ->numeric()
                        ->prefix('S/')
                        ->default(0)
                        ->disabled(fn ($record) => $record && $record->status === 'closed'),

                    Forms\Components\TextInput::make('closing_amount')
                        ->label('Monto de Cierre')
                        ->numeric()
                        ->prefix('S/')
                        ->disabled(fn ($record) => !$record || $record->status === 'open')
                        ->visible(fn ($record) => $record && $record->status === 'closed'),

                    Forms\Components\DateTimePicker::make('opened_at')
                        ->label('Fecha de Apertura')
                        ->default(now())
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('closed_at')
                        ->label('Fecha de Cierre')
                        ->disabled()
                        ->visible(fn ($record) => $record && $record->status === 'closed'),

                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options([
                            'open' => 'Abierta',
                            'closed' => 'Cerrada',
                        ])
                        ->disabled()
                        ->default('open'),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable(),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('opening_amount')
                    ->label('Monto Inicial')
                    ->money('PEN'),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('closing_amount')
                    ->label('Monto Final')
                    ->money('PEN'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'open' ? 'Abierta' : 'Cerrada')
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('opened_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'open' => 'Abierta',
                        'closed' => 'Cerrada',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('close')
                    ->label('Cerrar Caja')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (CashRegister $record) => $record->status === 'open')
                    ->form([
                        Forms\Components\TextInput::make('closing_amount')
                            ->label('Monto de Cierre')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->minValue(0),
                    ])
                    ->action(function (CashRegister $record, array $data) {
                        $record->close($data['closing_amount']);
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashRegisters::route('/'),
            'create' => Pages\CreateCashRegister::route('/create'),
            'view' => Pages\ViewCashRegister::route('/{record}'),
        ];
    }
}
