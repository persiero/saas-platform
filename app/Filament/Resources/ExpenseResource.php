<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use Percy\Core\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Gastos';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?int $navigationSort = 32;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    Forms\Components\Select::make('category')
                        ->label('Categoría')
                        ->options([
                            'Servicios' => 'Servicios',
                            'Suministros' => 'Suministros',
                            'Alquiler' => 'Alquiler',
                            'Salarios' => 'Salarios',
                            'Transporte' => 'Transporte',
                            'Marketing' => 'Marketing',
                            'Mantenimiento' => 'Mantenimiento',
                            'Otros' => 'Otros',
                        ])
                        ->required()
                        ->searchable(),

                    Forms\Components\TextInput::make('amount')
                        ->label('Monto')
                        ->required()
                        ->numeric()
                        ->prefix('S/')
                        ->minValue(0),

                    Forms\Components\DatePicker::make('expense_date')
                        ->label('Fecha del Gasto')
                        ->required()
                        ->default(now())
                        ->maxDate(now()),

                    Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Categoría')
                    ->searchable()
                    ->badge(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50),
            ])
            ->defaultSort('expense_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoría')
                    ->options([
                        'Servicios' => 'Servicios',
                        'Suministros' => 'Suministros',
                        'Alquiler' => 'Alquiler',
                        'Salarios' => 'Salarios',
                        'Transporte' => 'Transporte',
                        'Marketing' => 'Marketing',
                        'Mantenimiento' => 'Mantenimiento',
                        'Otros' => 'Otros',
                    ]),
                Tables\Filters\Filter::make('expense_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde'),
                        Forms\Components\DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('expense_date', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('expense_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
