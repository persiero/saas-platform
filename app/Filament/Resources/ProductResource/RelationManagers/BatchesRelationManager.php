<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Percy\Core\Models\ProductBatch;
use Percy\Core\Services\Inventory\InventoryService;

class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    protected static ?string $title = 'Lotes y Fechas de Vencimiento';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        if (self::hasLots()) {
            return 'Lotes y Fechas de Vencimiento';
        }

        return 'Fechas de Vencimiento';
    }

    private static function tenantFeatures(): array
    {
        return Auth::user()?->tenant?->businessSector?->features ?? [];
    }

    private static function hasLots(): bool
    {
        $features = self::tenantFeatures();

        return $features['has_lots'] ?? false;
    }

    private static function hasExpiryDates(): bool
    {
        $features = self::tenantFeatures();

        return $features['has_expiry_dates'] ?? false;
    }

    private static function usesBatchesOrExpiry(): bool
    {
        return self::hasLots() || self::hasExpiryDates();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('batch_number')
                    ->label('Número de Lote')
                    // 🌟 OCULTAMOS EL CAMPO PARA MINIMARKET
                    ->visible(function () {
                        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                        return $features['has_lots'] ?? false;
                    })
                    // 🌟 DEJA DE SER OBLIGATORIO PARA MINIMARKET
                    ->required(function () {
                        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                        return $features['has_lots'] ?? false;
                    })
                    ->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->columnSpan([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Forms\Components\DatePicker::make('manufacturing_date')
                    ->label('Fecha de Fabricación')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    // 🌟 UX: Ocultamos esto en minimarket también para que el formulario sea súper limpio
                    ->visible(function () {
                        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                        return $features['has_lots'] ?? false;
                    })
                    ->columnSpan(1),

                Forms\Components\DatePicker::make('expiration_date')
                    ->label('Fecha de Vencimiento') // 🌟 Le quité "(DIGEMID)" para que sirva tanto para medicinas como abarrotes
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate(now())
                    ->columnSpan(1), // No puede estar vencido al registrarlo

                Forms\Components\TextInput::make('initial_quantity')
                    ->label('Cantidad Ingresada')
                    ->required()
                    ->numeric()
                    // 🌟 1. Límites y saltos dinámicos leyendo al Producto "Padre"
                    ->step(fn(\Filament\Resources\RelationManagers\RelationManager $livewire) => $livewire->getOwnerRecord()->is_weighable ? 0.001 : 1)
                    ->minValue(fn(\Filament\Resources\RelationManagers\RelationManager $livewire) => $livewire->getOwnerRecord()->is_weighable ? 0.001 : 1)
                    ->default(1)
                    // 🌟 2. UX: Añadimos el sufijo visual (Kg, Lt, Und) para guiar al usuario
                    ->suffix(function (\Filament\Resources\RelationManagers\RelationManager $livewire) {
                        $product = $livewire->getOwnerRecord();
                        if (!$product->is_weighable) return 'Und';

                        $code = $product->unidadSunat?->codigo ?? 'NIU';
                        return match ($code) {
                            'KGM' => 'Kg',
                            'LTR' => 'Lt',
                            'GLL' => 'Gal',
                            default => 'Und',
                        };
                    })
                    // 🌟 3. BLINDAJE: Impide forzar decimales si el producto es por unidad
                    ->rules([
                        fn(\Filament\Resources\RelationManagers\RelationManager $livewire) => function (string $attribute, $value, \Closure $fail) use ($livewire) {
                            $product = $livewire->getOwnerRecord();
                            if (!$product->is_weighable && fmod((float)$value, 1) !== 0.0) {
                                $fail('Este producto solo admite cantidades enteras.');
                            }
                        },
                    ])
                    ->disabledOn('edit')
                    ->dehydrated()
                    ->columnSpan([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Forms\Components\Hidden::make('current_quantity')
                    ->default(fn(Forms\Get $get) => $get('initial_quantity')),

                Forms\Components\Hidden::make('tenant_id')
                    ->default(fn() => Auth::user()->tenant_id),
            ])
            ->columns([
                'default' => 1,
                'md' => 2,
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('batch_number')
            ->striped()
            ->paginated([5, 10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('heroicon-o-archive-box')
            ->emptyStateHeading(
                fn(): string => self::hasLots()
                    ? 'Sin lotes registrados'
                    : 'Sin vencimientos registrados'
            )
            ->emptyStateDescription(
                fn(): string => self::hasLots()
                    ? 'Registra el primer lote para controlar stock y vencimiento.'
                    : 'Registra una fecha de vencimiento para este producto.'
            )
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label(fn(): string => self::hasLots() ? 'Lote' : 'Vencimiento')
                    ->state(function (ProductBatch $record): string {
                        if (self::hasLots()) {
                            return $record->batch_number ?: 'Sin lote';
                        }

                        return $record->expiration_date
                            ? Carbon::parse($record->expiration_date)->format('d/m/Y')
                            : 'Sin vencimiento';
                    })
                    ->description(function (ProductBatch $record): string {
                        $stock = number_format(
                            (float) $record->current_quantity,
                            (float) $record->current_quantity == floor((float) $record->current_quantity) ? 0 : 3
                        );

                        $vencimiento = $record->expiration_date
                            ? Carbon::parse($record->expiration_date)->format('d/m/Y')
                            : 'Sin vencimiento';

                        return self::hasLots()
                            ? "Vence: {$vencimiento} · Stock: {$stock}"
                            : "Stock: {$stock}";
                    })
                    ->icon('heroicon-o-archive-box')
                    ->weight('bold')
                    ->wrap()
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('batch_number')
                    ->label(fn(): string => self::hasLots() ? 'Lote' : 'Código interno')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->badge()
                    ->color(fn(): string => self::hasLots() ? 'primary' : 'gray')
                    ->placeholder('Sin código')
                    ->visible(fn(): bool => self::usesBatchesOrExpiry())
                    ->description(
                        fn($record): ?string =>
                        self::hasLots()
                            ? null
                            : 'Generado automáticamente'
                    )
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('expiration_date')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->placeholder('Sin vencimiento')
                    ->color(function ($state): string {
                        if (! $state) {
                            return 'gray';
                        }

                        $fecha = Carbon::parse($state);

                        if ($fecha->isPast()) {
                            return 'danger';
                        }

                        if ($fecha->diffInDays(now()) <= 90) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Stock Actual')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        $value = (float) $state;

                        return number_format($value, $value == floor($value) ? 0 : 3);
                    })
                    ->color(fn($state): string => (float) $state <= 5 ? 'danger' : 'success')
                    ->visibleFrom('md'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(fn(): string => self::hasLots() ? 'Registrar Lote' : 'Registrar Vencimiento')
                    ->icon('heroicon-o-plus-circle')
                    ->modalWidth('3xl')
                    ->slideOver()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['current_quantity'] = $data['initial_quantity'];
                        $data['tenant_id'] = Auth::user()->tenant_id;

                        // 🌟 MAGIA PARA MINIMARKET: Si el lote viene vacío (porque está oculto), generamos uno interno
                        if (empty($data['batch_number'])) {
                            $data['batch_number'] = ! empty($data['expiration_date'])
                                ? 'VENC-' . Carbon::parse($data['expiration_date'])->format('Ymd') . '-' . now()->format('His')
                                : 'VENC-' . now()->format('Ymd-His');
                        } else {
                            $data['batch_number'] = strtoupper(trim($data['batch_number']));
                        }

                        return $data;
                    })
                    ->after(function (Model $record): void {
                        try {
                            app(InventoryService::class)->registerManualBatchStock(
                                $record,
                                'Registro Manual de Stock/Vencimiento'
                            );

                            Notification::make()
                                ->success()
                                ->title('Stock registrado')
                                ->body('El lote fue registrado y el stock ingresó correctamente al inventario.')
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('No se pudo registrar el stock')
                                ->body(collect($e->errors())->flatten()->first() ?? 'Verifica los datos del lote.')
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                // 🌟 NUEVA ACCIÓN DE EDITAR (Restringida y Segura)
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->tooltip('Editar lote / vencimiento')
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->color('warning')
                    ->modalWidth('3xl')
                    ->slideOver()
                    ->form([
                        Forms\Components\Section::make('Datos del lote / vencimiento')
                            ->description('Actualiza solo los datos informativos. El stock se protege por Kardex.')
                            ->schema([
                                Forms\Components\TextInput::make('batch_number')
                                    ->label('Número de Lote')
                                    ->maxLength(255)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->visible(function () {
                                        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                        return $features['has_lots'] ?? false;
                                    })
                                    ->required(function () {
                                        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                        return $features['has_lots'] ?? false;
                                    })
                                    ->columnSpanFull(),

                                Forms\Components\DatePicker::make('manufacturing_date')
                                    ->label('Fecha de Fabricación')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now())
                                    ->visible(function () {
                                        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                        return $features['has_lots'] ?? false;
                                    }),

                                Forms\Components\DatePicker::make('expiration_date')
                                    ->label('Fecha de Vencimiento')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->visible(function () {
                                        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                        return $features['has_expiry_dates'] ?? false;
                                    })
                                    ->required(function () {
                                        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                        return $features['has_expiry_dates'] ?? false;
                                    }),

                                Forms\Components\TextInput::make('current_quantity')
                                    ->label('Stock Actual')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->hintIcon(
                                        'heroicon-m-lock-closed',
                                        tooltip: 'El stock no se modifica manualmente. Se controla mediante movimientos de inventario y Kardex.'
                                    )
                                    ->columnSpanFull(),
                            ])
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ]),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        // Aseguramos que si editan el lote, se guarde en mayúsculas
                        if (isset($data['batch_number'])) {
                            $data['batch_number'] = strtoupper($data['batch_number']);
                        }
                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->tooltip('Eliminar lote / vencimiento')
                    ->icon('heroicon-o-trash')
                    ->iconButton()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar lote / vencimiento')
                    ->modalDescription('Esta acción retirará del inventario el stock restante de este lote y registrará el movimiento en Kardex.')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->before(function (Tables\Actions\DeleteAction $action, Model $record): void {
                        try {
                            app(InventoryService::class)->removeManualBatchStock(
                                $record,
                                'Eliminación manual de Lote: ' . $record->batch_number
                            );
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('No se pudo eliminar el lote')
                                ->body(collect($e->errors())->flatten()->first() ?? 'Verifica el stock antes de continuar.')
                                ->persistent()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                //Tables\Actions\BulkActionGroup::make([
                //  Tables\Actions\DeleteBulkAction::make(),
                //]),
            ]);
    }
}
