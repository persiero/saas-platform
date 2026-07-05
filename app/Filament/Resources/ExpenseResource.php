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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Services\Tenants\TenantPlanService;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?string $modelLabel = 'Gasto';
    protected static ?string $pluralModelLabel = 'Gastos';
    protected static ?int $navigationSort = 2;

    private static function userCanManageExpenses(): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            return false;
        }

        if (! $user->canManageCashRegister()) {
            return false;
        }

        return app(TenantPlanService::class)->has('has_expenses', $user->tenant);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->with(['user', 'cashRegister']);

        if (! $user?->isSuperAdmin()) {
            $query->where('tenant_id', $user?->tenant_id);
        }

        return $query;
    }

    /**
     * Oculta el módulo de Reportes para el Súper Admin
     */
    public static function canViewAny(): bool
    {
        return self::userCanManageExpenses();
    }

    // 🔒 1. Solo el Admin puede editar un gasto registrado
    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();

        if (! self::userCanManageExpenses()) {
            return false;
        }

        if (! $user?->isAdmin()) {
            return false;
        }

        $record->loadMissing('cashRegister');

        if ($record instanceof Expense && $record->cashRegister?->status === 'closed') {
            return false;
        }

        return true;
    }

    // 🔒 2. Solo el Admin puede eliminar un gasto individualmente
    public static function canDelete(Model $record): bool
    {
        $user = Auth::user();

        if (! self::userCanManageExpenses()) {
            return false;
        }

        if (! $user?->isAdmin()) {
            return false;
        }

        $record->loadMissing('cashRegister');

        if ($record instanceof Expense && $record->cashRegister?->status === 'closed') {
            return false;
        }

        return true;
    }

    // 🔒 3. Solo el Admin puede usar el botón rojo de borrado masivo
    public static function canDeleteAny(): bool
    {
        return self::userCanManageExpenses()
            && (Auth::user()?->isAdmin() ?? false);
    }

    public static function canCreate(): bool
    {
        return self::userCanManageExpenses();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Gasto')
                    ->description('Registra una salida de efectivo asociada a la caja abierta.')
                    ->icon('heroicon-o-banknotes')
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
                            ->hintIcon(
                                'heroicon-m-question-mark-circle',
                                tooltip: 'Selecciona la categoría que mejor describe este gasto.'
                            )
                            ->columnSpan([
                                'default' => 1,
                                'md' => 2,
                            ]),

                        Forms\Components\DatePicker::make('expense_date')
                            ->label('Fecha del Gasto')
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->hintIcon(
                                'heroicon-m-question-mark-circle',
                                tooltip: 'No puede ser una fecha futura.'
                            )
                            ->columnSpan([
                                'default' => 1,
                                'md' => 1,
                            ]),

                        Forms\Components\TextInput::make('amount')
                            ->label('Monto (S/)')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->minValue(0.01)
                            ->step(0.01)
                            ->placeholder('0.00')
                            ->hintIcon(
                                'heroicon-m-question-mark-circle',
                                tooltip: 'Ingresa el monto del gasto en soles peruanos.'
                            )
                            ->columnSpan([
                                'default' => 1,
                                'md' => 1,
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->placeholder('Describe el motivo o detalle del gasto...')
                            ->hintIcon(
                                'heroicon-m-question-mark-circle',
                                tooltip: 'Agrega información adicional para identificar este gasto en el arqueo de caja.'
                            )
                            ->columnSpanFull(),
                    ])->columns([
                        'default' => 1,
                        'md' => 3,
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Detalle del Gasto')
                    ->icon('heroicon-o-banknotes')
                    ->description('Información registrada para el arqueo de caja.')
                    ->schema([
                        TextEntry::make('category')
                            ->label('Categoría')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'Servicios' => 'info',
                                'Suministros' => 'warning',
                                'Alquiler' => 'danger',
                                'Salarios' => 'success',
                                'Transporte' => 'primary',
                                'Marketing' => 'purple',
                                'Mantenimiento' => 'orange',
                                'Otros' => 'gray',
                                default => 'gray',
                            }),

                        TextEntry::make('amount')
                            ->label('Monto')
                            ->money('PEN')
                            ->weight('black')
                            ->color('danger'),

                        TextEntry::make('expense_date')
                            ->label('Fecha del gasto')
                            ->date('d/m/Y')
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('user.name')
                            ->label('Registrado por')
                            ->icon('heroicon-o-user')
                            ->placeholder('No registrado'),

                        TextEntry::make('cashRegister.id')
                            ->label('Caja asociada')
                            ->formatStateUsing(fn($state): string => $state ? '#' . $state : 'Sin caja')
                            ->badge()
                            ->color(fn($state): string => $state ? 'success' : 'gray'),

                        TextEntry::make('created_at')
                            ->label('Fecha de registro')
                            ->dateTime('d/m/Y h:i A')
                            ->icon('heroicon-o-clock'),

                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('Sin descripción')
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
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Gasto')
                    ->state(fn(Expense $record): string => $record->category ?? 'Sin categoría')
                    ->description(function (Expense $record): string {
                        $fecha = $record->expense_date?->format('d/m/Y') ?? 'Sin fecha';
                        $monto = 'S/ ' . number_format((float) $record->amount, 2);
                        $usuario = $record->user?->name ?? 'Sin usuario';

                        return "{$fecha} · {$monto} · {$usuario}";
                    })
                    ->icon(fn(Expense $record): string => match ($record->category) {
                        'Servicios' => 'heroicon-o-briefcase',
                        'Suministros' => 'heroicon-o-cube',
                        'Alquiler' => 'heroicon-o-building-office',
                        'Salarios' => 'heroicon-o-users',
                        'Transporte' => 'heroicon-o-truck',
                        'Marketing' => 'heroicon-o-megaphone',
                        'Mantenimiento' => 'heroicon-o-wrench-screwdriver',
                        'Otros' => 'heroicon-o-ellipsis-horizontal-circle',
                        default => 'heroicon-o-banknotes',
                    })
                    ->weight('black')
                    ->wrap()
                    ->searchable(['category', 'description'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Rango de Fechas')
                    ->date('d/m/Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->description(fn(Expense $record): string => $record->expense_date->diffForHumans())
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Registrado por')
                    ->icon('heroicon-o-identification')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('cashRegister.id')
                    ->label('Caja')
                    ->formatStateUsing(fn($state) => $state ? '#' . $state : 'Sin caja')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'gray')
                    ->toggleable()
                    ->visibleFrom('xl'),

                Tables\Columns\TextColumn::make('category')
                    ->label('Categoría')
                    ->searchable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
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
                    ->icon(fn(string $state): string => match ($state) {
                        'Servicios' => 'heroicon-o-briefcase',
                        'Suministros' => 'heroicon-o-cube',
                        'Alquiler' => 'heroicon-o-building-office',
                        'Salarios' => 'heroicon-o-users',
                        'Transporte' => 'heroicon-o-truck',
                        'Marketing' => 'heroicon-o-megaphone',
                        'Mantenimiento' => 'heroicon-o-wrench-screwdriver',
                        'Otros' => 'heroicon-o-ellipsis-horizontal-circle',
                        default => 'heroicon-o-tag',
                    })
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable()
                    ->weight('bold')
                    ->color('danger')
                    ->icon('heroicon-o-banknotes')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(40)
                    ->searchable()
                    ->tooltip(fn(Expense $record): string => $record->description ?? 'Sin descripción')
                    ->wrap()
                    ->visibleFrom('lg'),

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
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn(Builder $query, $date) => $query->whereDate('expense_date', '>=', $date))
                            ->when($data['until'], fn(Builder $query, $date) => $query->whereDate('expense_date', '<=', $date));
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
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
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
                    ->tooltip('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
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
