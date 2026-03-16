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

class CashRegisterResource extends Resource
{
    protected static ?string $model = CashRegister::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Caja';
    protected static ?string $modelLabel = 'Caja';
    protected static ?string $pluralModelLabel = 'Cajas';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?int $navigationSort = 30;

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
                    ->visible(fn (CashRegister $record) => $record->status === 'open' && $record->user_id === Auth::id())
                    ->modalHeading('Cerrar Turno de Caja')
                    ->modalWidth('md') // Le damos un ancho mediano al modal
                    ->form([
                        // 1. EL RESUMEN INTELIGENTE
                        Forms\Components\Placeholder::make('resumen')
                            ->label('Resumen del Turno')
                            ->content(function (CashRegister $record) {
                                // Buscamos todas las ventas desde que se abrió esta caja
                                $sales = \Percy\Core\Models\Sale::where('tenant_id', $record->tenant_id)
                                    ->where('user_id', $record->user_id)
                                    ->where('sold_at', '>=', $record->opened_at)
                                    ->get();

                                // Buscamos todos los gastos registrados en este turno
                                $expenses = \Percy\Core\Models\Expense::where('tenant_id', $record->tenant_id)
                                    ->where('user_id', $record->user_id)
                                    ->where('created_at', '>=', $record->opened_at)
                                    ->sum('amount');

                                // Desglosamos por método de pago usando tu catálogo de opciones
                                $cashSales = $sales->where('payment_method', 'Efectivo')->sum('total');
                                $yapeSales = $sales->where('payment_method', 'Yape')->sum('total');
                                $plinSales = $sales->where('payment_method', 'Plin')->sum('total');
                                $cardSales = $sales->where('payment_method', 'Tarjeta')->sum('total');
                                $transferSales = $sales->where('payment_method', 'Transferencia')->sum('total');

                                // La matemática clave:
                                $expectedCash = $record->opening_amount + $cashSales - $expenses;

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
                                        <div class="flex justify-between"><span>Transferencias:</span> <span class="font-medium">S/ ' . number_format($transferSales, 2) . '</span></div>
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
                            ->helperText('Ingresa cuánto dinero físico hay realmente en la gaveta.')
                            ->extraInputAttributes(['class' => 'text-xl font-bold']), // Hace que el número se vea más grande al escribir
                    ])
                    ->action(function (CashRegister $record, array $data) {
                        $record->close($data['closing_amount']);
                        \Filament\Notifications\Notification::make()->title('Caja Cerrada Correctamente')->success()->send();
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
                                    ->state(function (CashRegister $record) {
                                        $endDate = $record->closed_at ?? now();
                                        return \Percy\Core\Models\Sale::where('tenant_id', $record->tenant_id)
                                            ->where('user_id', $record->user_id)
                                            ->whereBetween('sold_at', [$record->opened_at, $endDate])
                                            ->where('payment_method', 'Efectivo')
                                            ->sum('total');
                                    }),

                                TextEntry::make('calc_expenses')
                                    ->label('(-) Gastos Registrados')
                                    ->money('PEN')
                                    ->color('danger')
                                    ->state(function (CashRegister $record) {
                                        $endDate = $record->closed_at ?? now();
                                        return \Percy\Core\Models\Expense::where('tenant_id', $record->tenant_id)
                                            ->where('user_id', $record->user_id)
                                            ->whereBetween('created_at', [$record->opened_at, $endDate])
                                            ->sum('amount');
                                    }),

                                TextEntry::make('calc_expected')
                                    ->label('Efectivo Esperado')
                                    ->money('PEN')
                                    ->weight('bold')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->state(function (CashRegister $record) {
                                        $endDate = $record->closed_at ?? now();
                                        $cashSales = \Percy\Core\Models\Sale::where('tenant_id', $record->tenant_id)
                                            ->where('user_id', $record->user_id)
                                            ->whereBetween('sold_at', [$record->opened_at, $endDate])
                                            ->where('payment_method', 'Efectivo')
                                            ->sum('total');

                                        $expenses = \Percy\Core\Models\Expense::where('tenant_id', $record->tenant_id)
                                            ->where('user_id', $record->user_id)
                                            ->whereBetween('created_at', [$record->opened_at, $endDate])
                                            ->sum('amount');

                                        return $record->opening_amount + $cashSales - $expenses;
                                    }),

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
                                    ->state(function (CashRegister $record) {
                                        if ($record->status === 'open') return 0;

                                        $cashSales = \Percy\Core\Models\Sale::where('tenant_id', $record->tenant_id)
                                            ->where('user_id', $record->user_id)
                                            ->whereBetween('sold_at', [$record->opened_at, $record->closed_at])
                                            ->where('payment_method', 'Efectivo')
                                            ->sum('total');

                                        $expenses = \Percy\Core\Models\Expense::where('tenant_id', $record->tenant_id)
                                            ->where('user_id', $record->user_id)
                                            ->whereBetween('created_at', [$record->opened_at, $record->closed_at])
                                            ->sum('amount');

                                        $expected = $record->opening_amount + $cashSales - $expenses;
                                        return $record->closing_amount - $expected;
                                    }),
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
                                        ->state(function (CashRegister $record) use ($method) {
                                            $endDate = $record->closed_at ?? now();
                                            return \Percy\Core\Models\Sale::where('tenant_id', $record->tenant_id)
                                                ->where('user_id', $record->user_id)
                                                ->whereBetween('sold_at', [$record->opened_at, $endDate])
                                                ->where('payment_method', $method)
                                                ->sum('total');
                                        });
                                }),

                                TextEntry::make('calc_total_sales')
                                    ->label('TOTAL VENTAS')
                                    ->money('PEN')
                                    ->weight('black')
                                    ->color('primary')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->state(function (CashRegister $record) {
                                        $endDate = $record->closed_at ?? now();
                                        return \Percy\Core\Models\Sale::where('tenant_id', $record->tenant_id)
                                            ->where('user_id', $record->user_id)
                                            ->whereBetween('sold_at', [$record->opened_at, $endDate])
                                            ->sum('total');
                                    }),
                            ])->columns(2),

                        Section::make('Rendimiento del Turno')
                            ->schema([
                                TextEntry::make('calc_sales_count')
                                    ->label('Ventas Realizadas')
                                    ->badge()
                                    ->state(function (CashRegister $record) {
                                        $endDate = $record->closed_at ?? now();
                                        return \Percy\Core\Models\Sale::where('tenant_id', $record->tenant_id)
                                            ->where('user_id', $record->user_id)
                                            ->whereBetween('sold_at', [$record->opened_at, $endDate])
                                            ->count();
                                    }),

                                TextEntry::make('calc_average_ticket')
                                    ->label('Ticket Promedio')
                                    ->money('PEN')
                                    ->state(function (CashRegister $record) {
                                        $endDate = $record->closed_at ?? now();
                                        $count = \Percy\Core\Models\Sale::where('tenant_id', $record->tenant_id)
                                            ->where('user_id', $record->user_id)
                                            ->whereBetween('sold_at', [$record->opened_at, $endDate])
                                            ->count();

                                        if ($count === 0) return 0;

                                        $total = \Percy\Core\Models\Sale::where('tenant_id', $record->tenant_id)
                                            ->where('user_id', $record->user_id)
                                            ->whereBetween('sold_at', [$record->opened_at, $endDate])
                                            ->sum('total');

                                        return $total / $count;
                                    }),
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
}
