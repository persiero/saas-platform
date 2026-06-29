<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashRegisterResource\Pages;
use Percy\Core\Models\CashRegister;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Sale;
use Percy\Core\Models\Expense;
use Percy\Core\Services\Cash\CashRegisterService;
use App\Filament\Resources\CashRegisterResource\RelationManagers;

class CashRegisterResource extends Resource
{
    protected static ?string $model = CashRegister::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?string $modelLabel = 'Caja';
    protected static ?string $pluralModelLabel = 'Cajas';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->with(['user']);

        if (!$user?->isSuperAdmin()) {
            $query->where('tenant_id', $user?->tenant_id);
        }

        return $query;
    }

    /**
     * Oculta el módulo de Reportes para el Súper Admin
     */
    public static function canViewAny(): bool
    {
        return Auth::user()?->canViewCashRegisters() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('opening_amount')
                    ->label('Monto Inicial en Efectivo')
                    ->required()
                    ->numeric()
                    ->prefix('S/')
                    ->default(0)
                    ->helperText('Dinero físico en caja al iniciar el turno.')
                    ->disabled(fn ($record) => $record && $record->status === 'closed')
                    ->columnSpanFull(),

                // Ocultamos los demás campos en el formulario, ya que se llenan solos o en el cierre
                Forms\Components\Hidden::make('status')->default('open'),
                Forms\Components\Hidden::make('opened_at')->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordClasses(fn (CashRegister $record) => match ($record->status) {
                'open' => 'bg-success-50 dark:bg-success-900/20 border-l-4 border-success-600', // Resalta la caja abierta en verde suave
                default => null,
            })
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->icon('heroicon-o-user')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->since()
                    ->description(fn (CashRegister $record): string => $record->opened_at->format('d/m/Y H:i')),

                Tables\Columns\TextColumn::make('opening_amount')
                    ->label('Monto Inicial')
                    ->money('PEN')
                    ->icon('heroicon-o-banknotes')
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-lock-closed')
                    ->placeholder('Aún abierta')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('closing_amount')
                    ->label('Monto Final')
                    ->money('PEN')
                    ->icon('heroicon-o-banknotes')
                    ->weight('semibold')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'open' ? 'Abierta' : 'Cerrada')
                    ->icon(fn (string $state): string => $state === 'open' ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'gray',
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
                    ])
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\Action::make('close')
                    ->label('Cerrar Caja')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->button()
                    // 🌟 MAGIA DE SEGURIDAD: Solo el dueño de la caja ve el botón
                    ->visible(fn (CashRegister $record) =>
                        $record->status === 'open' &&
                        (Auth::user()?->canCloseCashRegisterRecord($record) ?? false)
                    )
                    ->modalHeading('Cerrar Turno de Caja')
                    ->modalWidth('md') // Le damos un ancho mediano al modal
                    ->form([
                        // 1. EL RESUMEN INTELIGENTE
                        Forms\Components\Placeholder::make('resumen')
                            ->label('Resumen del Turno')
                            ->content(function (CashRegister $record) {
                                // 🌟 1. VENTAS: Traemos TODO lo que se haya registrado bajo el ID de esta caja (Adiós a los filtros de fecha y usuario)
                                $expenses = self::expensesTotal($record);

                                $cashSales = self::salesTotalByMethod($record, 'Efectivo');
                                $yapeSales = self::salesTotalByMethod($record, 'Yape');
                                $plinSales = self::salesTotalByMethod($record, 'Plin');
                                $cardSales = self::salesTotalByMethod($record, 'Tarjeta');
                                $transferSales = self::salesTotalByMethod($record, 'Transferencia');

                                $totalSales = self::salesTotal($record);
                                $expectedCash = self::expectedCash($record);

                                // Armamos el diseño visual directamente en HTML con clases de Tailwind
                                $html = '
                                <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                                    <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="flex justify-between"><span>Fondo Inicial:</span> <span class="font-medium">S/ ' . number_format($record->opening_amount, 2) . '</span></div>
                                        <div class="flex justify-between text-success-600 dark:text-success-400"><span>(+) Ventas Efectivo:</span> <span class="font-medium">S/ ' . number_format($cashSales, 2) . '</span></div>
                                        <div class="flex justify-between text-danger-600 dark:text-danger-400 border-b border-gray-300 dark:border-gray-600 pb-2"><span>(-) Gastos Registrados:</span> <span class="font-medium">S/ ' . number_format($expenses, 2) . '</span></div>
                                        <div class="flex justify-between text-lg pt-2 text-gray-900 dark:text-white">
                                            <span class="font-bold">Efectivo Esperado:</span>
                                            <span class="font-black text-primary-600 dark:text-primary-400">S/ ' . number_format($expectedCash, 2) . '</span>
                                        </div>
                                    </div>

                                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                        <div class="font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Ingresos Digitales (No en cajón)</div>
                                        <div class="flex justify-between"><span>Yape:</span> <span class="font-medium">S/ ' . number_format($yapeSales, 2) . '</span></div>
                                        <div class="flex justify-between"><span>Plin:</span> <span class="font-medium">S/ ' . number_format($plinSales, 2) . '</span></div>
                                        <div class="flex justify-between"><span>Tarjetas:</span> <span class="font-medium">S/ ' . number_format($cardSales, 2) . '</span></div>
                                        <div class="flex justify-between border-b border-gray-300 dark:border-gray-600 pb-2"><span>Transferencias:</span> <span class="font-medium">S/ ' . number_format($transferSales, 2) . '</span></div>

                                        <div class="flex justify-between text-base pt-3 text-gray-900 dark:text-white">
                                            <span class="font-bold">Total Ventas del Turno:</span>
                                            <span class="font-black text-primary-600 dark:text-primary-400">S/ ' . number_format($totalSales, 2) . '</span>
                                        </div>
                                    </div>
                                </div>
                                ';

                                return new HtmlString($html);
                            }),

                        // 2. EL INPUT PARA QUE EL CAJERO DECLARE
                        Forms\Components\TextInput::make('closing_amount')
                            ->label('Efectivo Físico Contado')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->live(onBlur: true)
                            ->helperText('Ingresa cuánto dinero físico hay realmente en la gaveta.')
                            ->extraInputAttributes(['class' => 'text-xl font-bold']), // Hace que el número se vea más grande al escribir

                        Forms\Components\Placeholder::make('cash_difference_preview')
                            ->label('Resultado del Arqueo')
                            ->content(function (CashRegister $record, Forms\Get $get) {
                                $closingAmount = $get('closing_amount');

                                if ($closingAmount === null || $closingAmount === '') {
                                    return new HtmlString(
                                        '<div class="text-sm text-gray-500 dark:text-gray-400">
                                            Ingresa el efectivo contado para calcular si la caja cuadra.
                                        </div>'
                                    );
                                }

                                $expectedCash = self::expectedCash($record);
                                $countedCash = (float) $closingAmount;
                                $difference = round($countedCash - $expectedCash, 2);

                                if (abs($difference) < 0.01) {
                                    return new HtmlString(
                                        '<div class="rounded-xl border border-success-300 bg-success-50 p-3 text-success-700 dark:bg-success-900/20 dark:text-success-300">
                                            <strong>✓ Caja cuadrada.</strong><br>
                                            No existe diferencia entre el efectivo esperado y el efectivo contado.
                                        </div>'
                                    );
                                }

                                if ($difference < 0) {
                                    return new HtmlString(
                                        '<div class="rounded-xl border border-danger-300 bg-danger-50 p-3 text-danger-700 dark:bg-danger-900/20 dark:text-danger-300">
                                            <strong>⚠ Faltante de caja:</strong> S/ ' . number_format(abs($difference), 2) . '<br>
                                            El efectivo contado es menor al efectivo esperado.
                                        </div>'
                                    );
                                }

                                return new HtmlString(
                                    '<div class="rounded-xl border border-warning-300 bg-warning-50 p-3 text-warning-700 dark:bg-warning-900/20 dark:text-warning-300">
                                        <strong>⚠ Sobrante de caja:</strong> S/ ' . number_format($difference, 2) . '<br>
                                        El efectivo contado es mayor al efectivo esperado.
                                    </div>'
                                );
                            }),

                        Forms\Components\Textarea::make('closing_notes')
                            ->label('Observación del cierre')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Obligatorio si la caja tiene faltante o sobrante.')
                            ->required(function (CashRegister $record, Forms\Get $get) {
                                $closingAmount = $get('closing_amount');

                                if ($closingAmount === null || $closingAmount === '') {
                                    return false;
                                }

                                $difference = round((float) $closingAmount - self::expectedCash($record), 2);

                                return abs($difference) >= 0.01;
                            })
                            ->visible(function (CashRegister $record, Forms\Get $get) {
                                $closingAmount = $get('closing_amount');

                                if ($closingAmount === null || $closingAmount === '') {
                                    return false;
                                }

                                $difference = round((float) $closingAmount - self::expectedCash($record), 2);

                                return abs($difference) >= 0.01;
                            }),
                    ])
                    ->action(function (CashRegister $record, array $data) {
                        $closingAmount = (float) $data['closing_amount'];

                        $closedCashRegister = self::cashRegisterService()->closeCashRegister(
                            $record,
                            $closingAmount,
                            $data['closing_notes'] ?? null,
                            Auth::id()
                        );

                        $difference = (float) $closedCashRegister->cash_difference;

                        if (abs($difference) < 0.01) {
                            Notification::make()
                                ->title('Caja Cerrada Correctamente')
                                ->body('La caja cuadró sin diferencias.')
                                ->success()
                                ->send();

                            return;
                        }

                        if ($difference < 0) {
                            Notification::make()
                                ->title('Caja cerrada con faltante')
                                ->body('Faltante detectado: S/ ' . number_format(abs($difference), 2))
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Caja cerrada con sobrante')
                            ->body('Sobrante detectado: S/ ' . number_format($difference, 2))
                            ->warning()
                            ->persistent()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Cerrar Turno de Caja'),

                // Acciones secundarias agrupadas
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()->label('Detalles'),
                ])->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Sin cajas registradas')
            ->emptyStateDescription('Abre tu primera caja para comenzar a registrar ventas')
            ->emptyStateIcon('heroicon-o-calculator');
    }

    private static function cashRegisterService(): CashRegisterService
    {
        return app(CashRegisterService::class);
    }

    private static function salesTotalByMethod(CashRegister $record, string $paymentMethod): float
    {
        return self::cashRegisterService()->salesTotalByMethod($record, $paymentMethod);
    }

    private static function salesTotal(CashRegister $record): float
    {
        return self::cashRegisterService()->salesTotal($record);
    }

    private static function salesCount(CashRegister $record): int
    {
        return self::cashRegisterService()->salesCount($record);
    }

    private static function expensesTotal(CashRegister $record): float
    {
        return self::cashRegisterService()->expensesTotal($record);
    }

    private static function expectedCash(CashRegister $record): float
    {
        return self::cashRegisterService()->expectedCash($record);
    }

    private static function expectedCashForDisplay(CashRegister $record): float
    {
        return self::cashRegisterService()->expectedCashForDisplay($record);
    }

    private static function cashDifference(CashRegister $record): float
    {
        return self::cashRegisterService()->cashDifference($record);
    }

    private static function cashDifferenceForDisplay(CashRegister $record): float
    {
        return self::cashRegisterService()->cashDifferenceForDisplay($record);
    }

    private static function averageTicket(CashRegister $record): float
    {
        return self::cashRegisterService()->averageTicket($record);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Grid::make(3)->schema([
                    // COLUMNA 1: Datos del Turno y Auditoría (Resumen de Efectivo)
                    Grid::make(1)->schema([
                        Section::make('Turno de Caja')
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Usuario')
                                    ->icon('heroicon-o-user')
                                    ->weight('bold'),

                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'open' => 'success',
                                        'closed' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn ($state) => $state === 'open' ? 'Abierta' : 'Cerrada'),

                                TextEntry::make('opened_at')
                                    ->label('Fecha Apertura')
                                    ->dateTime('d/m/Y H:i'),

                                TextEntry::make('closed_at')
                                    ->label('Fecha Cierre')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('Aún abierta'),
                            ])->columns(2),

                        Section::make('Auditoría de Efectivo (Cajón)')
                            ->description('Dinero físico manejado durante el turno.')
                            ->schema([
                                TextEntry::make('opening_amount')
                                    ->label('Fondo Inicial')
                                    ->money('PEN'),

                                // --- AQUÍ COMIENZA LA MAGIA DE LOS CÁLCULOS AL VUELO ---

                                TextEntry::make('calc_cash_sales')
                                    ->label('(+) Ventas en Efectivo')
                                    ->money('PEN')
                                    ->color('success')
                                    ->state(fn (CashRegister $record) => self::salesTotalByMethod($record, 'Efectivo')),

                                TextEntry::make('calc_expenses')
                                    ->label('(-) Gastos Registrados')
                                    ->money('PEN')
                                    ->color('danger')
                                    ->state(fn (CashRegister $record) => self::expensesTotal($record)),

                                TextEntry::make('calc_expected')
                                    ->label('Efectivo Esperado')
                                    ->money('PEN')
                                    ->weight('bold')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->state(fn (CashRegister $record) => self::expectedCashForDisplay($record)),

                                TextEntry::make('closing_amount') // ESTA SÍ ES TU COLUMNA REAL
                                    ->label('Efectivo Contado')
                                    ->money('PEN')
                                    ->weight('bold')
                                    ->placeholder('Esperando cierre...'),

                                TextEntry::make('calc_difference')
                                    ->label('Diferencia')
                                    ->money('PEN')
                                    ->weight('bold')
                                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'warning' : 'success'))
                                    ->state(fn (CashRegister $record) => self::cashDifferenceForDisplay($record)),

                                TextEntry::make('closedBy.name')
                                    ->label('Cerrado por')
                                    ->icon('heroicon-o-identification')
                                    ->placeholder('Pendiente de cierre'),

                                TextEntry::make('closing_notes')
                                    ->label('Observación del cierre')
                                    ->placeholder('Sin observaciones')
                                    ->columnSpanFull(),

                            ])->columns(2),
                    ])->columnSpan(2),

                    // COLUMNA 2: Desglose Digital y Estadísticas
                    Grid::make(1)->schema([
                        Section::make('Ventas por Método de Pago')
                            ->schema([
                                // Función de ayuda para no repetir tanto código
                                ...collect(['Yape', 'Plin', 'Tarjeta', 'Transferencia'])->map(function ($method) {
                                    return TextEntry::make("calc_sales_{$method}")
                                        ->label($method)
                                        ->money('PEN')
                                        ->state(fn (CashRegister $record) => self::salesTotalByMethod($record, $method));
                                }),

                                TextEntry::make('calc_total_sales')
                                    ->label('TOTAL VENTAS')
                                    ->money('PEN')
                                    ->weight('black')
                                    ->color('primary')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->state(fn (CashRegister $record) => self::salesTotal($record)),
                            ])->columns(2),

                        Section::make('Rendimiento del Turno')
                            ->schema([
                                TextEntry::make('calc_sales_count')
                                    ->label('Ventas Realizadas')
                                    ->badge()
                                    ->state(fn (CashRegister $record) => self::salesCount($record)),

                                TextEntry::make('calc_average_ticket')
                                    ->label('Ticket Promedio')
                                    ->money('PEN')
                                    ->state(fn (CashRegister $record) => self::averageTicket($record)),
                            ])->columns(2),
                    ])->columnSpan(1),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashRegisters::route('/'),
            //'create' => Pages\CreateCashRegister::route('/create'),
            'view' => Pages\ViewCashRegister::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SalesRelationManager::class,
        ];
    }

    // Añade esto al final de CashRegisterResource.php

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // Nadie edita una caja. Se abre, se cierra, y queda en el historial.
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // Las cajas jamás se borran
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
