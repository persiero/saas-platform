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
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?string $modelLabel = 'Gasto';
    protected static ?string $pluralModelLabel = 'Gastos';
    protected static ?int $navigationSort = 2;

    /**
     * Oculta el módulo de Reportes para el Súper Admin
     */
    public static function canViewAny(): bool
    {
        // Retorna TRUE (lo muestra) solo si el usuario pertenece a una empresa
        return \Illuminate\Support\Facades\Auth::user()->tenant_id !== null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id);
    }

    // 🔒 1. Solo el Admin puede editar un gasto registrado
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    // 🔒 2. Solo el Admin puede eliminar un gasto individualmente
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    // 🔒 3. Solo el Admin puede usar el botón rojo de borrado masivo
    public static function canDeleteAny(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Gasto')
                    ->description('Registra los detalles del gasto operativo')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\Select::make('category')
                            ->label('Categoría')
                            ->options([
                                'Servicios' => '💼 Servicios',
                                'Suministros' => '📦 Suministros',
                                'Alquiler' => '🏢 Alquiler',
                                'Salarios' => '👥 Salarios',
                                'Transporte' => '🚗 Transporte',
                                'Marketing' => '📢 Marketing',
                                'Mantenimiento' => '🔧 Mantenimiento',
                                'Otros' => '📋 Otros',
                            ])
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->helperText('Selecciona la categoría que mejor describe este gasto')
                            ->columnSpan(2),

                        Forms\Components\DatePicker::make('expense_date')
                            ->label('Fecha del Gasto')
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->helperText('No puede ser una fecha futura')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('amount')
                            ->label('Monto (S/)')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->minValue(0.01)
                            ->step(0.01)
                            ->placeholder('0.00')
                            ->helperText('Ingresa el monto en soles peruanos')
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(4)
                            ->placeholder('Describe el motivo o detalle del gasto...')
                            ->helperText('Proporciona información adicional que ayude a identificar este gasto')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Rango de Fechas')
                    ->date('d/m/Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->description(fn (Expense $record): string => $record->expense_date->diffForHumans()),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Registrado por')
                    ->icon('heroicon-o-identification')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Categoría')
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Servicios' => 'info',
                        'Suministros' => 'warning',
                        'Alquiler' => 'danger',
                        'Salarios' => 'success',
                        'Transporte' => 'primary',
                        'Marketing' => 'purple',
                        'Mantenimiento' => 'orange',
                        'Otros' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Servicios' => 'heroicon-o-briefcase',
                        'Suministros' => 'heroicon-o-cube',
                        'Alquiler' => 'heroicon-o-building-office',
                        'Salarios' => 'heroicon-o-users',
                        'Transporte' => 'heroicon-o-truck',
                        'Marketing' => 'heroicon-o-megaphone',
                        'Mantenimiento' => 'heroicon-o-wrench-screwdriver',
                        'Otros' => 'heroicon-o-ellipsis-horizontal-circle',
                        default => 'heroicon-o-tag',
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable()
                    ->weight('bold')
                    ->color('danger')
                    ->icon('heroicon-o-banknotes'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(40)
                    ->searchable()
                    ->tooltip(fn (Expense $record): string => $record->description ?? 'Sin descripción')
                    ->wrap(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('expense_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoría')
                    ->multiple()
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
                    ->indicator('Categoría'),

                Tables\Filters\Filter::make('expense_date')
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
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('expense_date', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('expense_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Desde: ' . \Carbon\Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Hasta: ' . \Carbon\Carbon::parse($data['until'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver detalles')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->modalHeading('Detalle del Gasto')
                        ->modalCancelActionLabel('Cerrar'),
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil'),
                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Gasto')
                        ->modalDescription('¿Estás seguro de que deseas eliminar este gasto? Esta acción no se puede deshacer.')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->modalCancelActionLabel('Cancelar'),
                ])
                ->label('Acciones')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Gastos')
                        ->modalDescription('¿Estás seguro de que deseas eliminar los gastos seleccionados?')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->modalCancelActionLabel('Cancelar'),
                ]),
            ])
            ->emptyStateHeading('No hay gastos registrados')
            ->emptyStateDescription('Comienza registrando tu primer gasto usando el botón de arriba.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            //'create' => Pages\CreateExpense::route('/create'),
            //'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
